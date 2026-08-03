<?php

/**
 * Provisioning orchestration.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provisioning;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Support\Settings;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Drives one machine from paid to running.
 *
 * An attempt either creates the machine or, when it was created earlier but not
 * resolved yet, only resolves it. Every attempt is counted and stamped, so the
 * retry job can pick up exactly the rows that are due and stop once the
 * configured limit is reached.
 */
final class ProvisioningService
{
    /**
     * Guard against two requests provisioning the same row at once.
     */
    private const LOCK_PREFIX = 'cvm_provisioning_lock_';
    private const LOCK_TTL = 300;

    /**
     * @var VmProvisioner
     */
    private $provisioner;

    /**
     * @var VmResolver
     */
    private $resolver;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        VmProvisioner $provisioner,
        VmResolver $resolver,
        VmOrderRepository $orders,
        ProviderRepository $providers,
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->provisioner = $provisioner;
        $this->resolver = $resolver;
        $this->orders = $orders;
        $this->providers = $providers;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    /**
     * Provision every machine of a WooCommerce order.
     *
     * @return ProvisioningResult[] Keyed by local order row identifier.
     */
    public function provisionWooCommerceOrder(int $wcOrderId): array
    {
        $results = [];

        foreach ($this->orders->forWooCommerceOrder($wcOrderId) as $order) {
            if ($order->isProvisioned() || $order->isTerminated()) {
                continue;
            }

            $results[$order->id()] = $this->provision($order);
        }

        return $results;
    }

    /**
     * Run one provisioning attempt for a machine.
     */
    public function provision(VmOrder $order): ProvisioningResult
    {
        if ($this->isLocked($order->id())) {
            return ProvisioningResult::pending(
                __('Another attempt for this machine is already running.', 'cloud-vm-manager')
            );
        }

        $provider = $this->providers->findProvider($order->getProviderId());

        if (!$provider instanceof Provider) {
            $result = ProvisioningResult::permanentFailure(
                __('The provider of this machine no longer exists.', 'cloud-vm-manager')
            );

            $this->record($order, $result);

            return $result;
        }

        $this->lock($order->id());

        try {
            $this->orders->update(
                $order->id(),
                [
                    'status' => VmOrder::STATUS_PROVISIONING,
                    'provisioning_status' => VmOrder::PROVISIONING_RUNNING,
                ]
            );

            $result = $this->attempt($provider, $order);
        } catch (Throwable $exception) {
            $this->logger->exception(
                $exception,
                'Provisioning failed unexpectedly.',
                [
                    'channel' => LogEntry::CHANNEL_PROVISIONING,
                    'vm_order_id' => $order->id(),
                    'wc_order_id' => $order->getWcOrderId(),
                ]
            );

            $result = ProvisioningResult::temporaryFailure($exception->getMessage());
        } finally {
            $this->unlock($order->id());
        }

        $this->record($order, $result);

        return $result;
    }

    /**
     * Provision every machine whose retry is due.
     *
     * @return ProvisioningResult[]
     */
    public function processRetryQueue(int $limit = 10): array
    {
        $results = [];

        foreach ($this->orders->dueForRetry($limit) as $order) {
            if (!$order->canRetry($this->retryLimit())) {
                $this->exhaust($order);

                continue;
            }

            $results[$order->id()] = $this->provision($order);
        }

        return $results;
    }

    /**
     * Create the machine when it does not exist yet, then resolve it.
     */
    private function attempt(Provider $provider, VmOrder $order): ProvisioningResult
    {
        if ($order->getGroupId() === '' && $order->getRemoteOrderId() === '') {
            $result = $this->provisioner->create($provider, $order);

            if ($result->isFailed()) {
                return $result;
            }

            $order = $this->orders->findOrder($order->id()) ?? $order;
        }

        $details = $this->resolver->resolve($provider, $order);

        if ($details === []) {
            return ProvisioningResult::pending(
                __('The machine is not listed by the backend yet.', 'cloud-vm-manager')
            );
        }

        $this->orders->update(
            $order->id(),
            array_merge(
                $details,
                [
                    'status' => VmOrder::STATUS_ACTIVE,
                    'provisioning_status' => VmOrder::PROVISIONING_COMPLETED,
                    'provisioned_at' => gmdate('Y-m-d H:i:s'),
                    'renews_at' => $this->renewalDate($order),
                    'error_message' => '',
                    'next_retry_at' => null,
                ]
            )
        );

        $this->logger->info(
            sprintf('Machine %d provisioned for order %d.', (int) $details['remote_vm_id'], $order->getWcOrderId()),
            [
                'channel' => LogEntry::CHANNEL_PROVISIONING,
                'provider_id' => $provider->id(),
                'vm_order_id' => $order->id(),
                'wc_order_id' => $order->getWcOrderId(),
                'user_id' => $order->getUserId(),
            ]
        );

        /**
         * Fires once a machine finished provisioning.
         *
         * @param VmOrder              $order   Local order row.
         * @param array<string, mixed> $details Fields read back from the backend.
         */
        do_action('cloud_vm_manager_vm_provisioned', $order, $details);

        return ProvisioningResult::completed(__('The machine is ready.', 'cloud-vm-manager'), $details);
    }

    /**
     * Store the outcome of an attempt and schedule the next one when needed.
     */
    private function record(VmOrder $order, ProvisioningResult $result): void
    {
        if ($result->isCompleted()) {
            return;
        }

        $attempts = $order->getAttempts() + 1;
        $canRetry = $result->isRetryable() && $attempts < $this->retryLimit();

        if (!$canRetry) {
            $this->orders->update(
                $order->id(),
                [
                    'attempts' => $attempts,
                    'status' => VmOrder::STATUS_FAILED,
                    'provisioning_status' => VmOrder::PROVISIONING_FAILED,
                    'last_attempt_at' => gmdate('Y-m-d H:i:s'),
                    'next_retry_at' => null,
                    'error_message' => $result->message(),
                ]
            );

            $this->logger->error(
                sprintf('Provisioning of order %d gave up after %d attempts.', $order->getWcOrderId(), $attempts),
                [
                    'channel' => LogEntry::CHANNEL_PROVISIONING,
                    'vm_order_id' => $order->id(),
                    'wc_order_id' => $order->getWcOrderId(),
                ]
            );

            /**
             * Fires when a machine could not be provisioned.
             *
             * @param VmOrder $order   Local order row.
             * @param string  $message Reason of the final failure.
             */
            do_action('cloud_vm_manager_provisioning_failed', $order, $result->message());

            return;
        }

        $delay = $this->retryDelay() * max(1, $attempts);

        $this->orders->update(
            $order->id(),
            [
                'attempts' => $attempts,
                'status' => VmOrder::STATUS_PROVISIONING,
                'provisioning_status' => VmOrder::PROVISIONING_RETRYING,
                'last_attempt_at' => gmdate('Y-m-d H:i:s'),
                'next_retry_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                'error_message' => $result->message(),
            ]
        );

        $this->logger->warning(
            sprintf('Provisioning of order %d will be retried in %d seconds.', $order->getWcOrderId(), $delay),
            [
                'channel' => LogEntry::CHANNEL_PROVISIONING,
                'vm_order_id' => $order->id(),
                'wc_order_id' => $order->getWcOrderId(),
                'attempt' => $attempts,
            ]
        );
    }

    /**
     * Mark a row that ran out of attempts.
     */
    private function exhaust(VmOrder $order): void
    {
        $this->orders->update(
            $order->id(),
            [
                'status' => VmOrder::STATUS_FAILED,
                'provisioning_status' => VmOrder::PROVISIONING_FAILED,
                'next_retry_at' => null,
            ]
        );
    }

    /**
     * Date the machine renews on, based on the billing cycle.
     */
    private function renewalDate(VmOrder $order): string
    {
        return gmdate('Y-m-d H:i:s', strtotime('+' . $order->getMonths() . ' months') ?: time());
    }

    private function retryLimit(): int
    {
        return max(1, $this->settings->getInt('provisioning_retry_limit', 5));
    }

    private function retryDelay(): int
    {
        return max(30, $this->settings->getInt('provisioning_retry_delay', 300));
    }

    private function isLocked(int $orderId): bool
    {
        return get_transient(self::LOCK_PREFIX . $orderId) !== false;
    }

    private function lock(int $orderId): void
    {
        set_transient(self::LOCK_PREFIX . $orderId, 1, self::LOCK_TTL);
    }

    private function unlock(int $orderId): void
    {
        delete_transient(self::LOCK_PREFIX . $orderId);
    }
}
