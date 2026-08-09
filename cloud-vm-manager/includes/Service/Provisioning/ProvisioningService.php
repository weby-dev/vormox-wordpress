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
     * Hook of the one-off check queued right after a machine is created.
     */
    public const BUILD_CHECK_HOOK = 'cvm_provisioning_build_check';

    /**
     * Guard against two requests provisioning the same row at once.
     */
    private const LOCK_PREFIX = 'cvm_provisioning_lock_';
    private const LOCK_TTL = 300;

    /**
     * Seconds between checks of a machine that is starting up. A machine takes
     * up to a minute, so this is short enough to notice it quickly and long
     * enough not to poll the backend pointlessly.
     */
    private const BUILD_POLL_DELAY = 20;

    /**
     * How long a machine may take to start up before it is escalated.
     */
    private const BUILD_TIMEOUT = 1800;

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

        /* The attempt may have stored identifiers, so record against fresh state. */
        $this->record($this->orders->findOrder($order->id()) ?? $order, $result);

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
            /*
             * A machine that is starting up is waited on, not retried, so the
             * creation retry budget does not apply to it.
             */
            if (!$order->isBuilding() && !$order->canRetry($this->retryLimit())) {
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
        if (!$this->wasCreated($order)) {
            $result = $this->provisioner->create($provider, $order);

            if ($result->isFailed()) {
                return $result;
            }

            $order = $this->orders->findOrder($order->id()) ?? $order;
            $this->splitBulkMachines($order, $result);
        }

        $details = $this->resolver->resolve($provider, $order);

        if ($details === []) {
            if ($order->getRemoteVmId() > 0) {
                return ProvisioningResult::building(
                    __('The machine was created and is starting up.', 'cloud-vm-manager')
                );
            }

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
                    'building_since' => null,
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
     * Whether a creation request for this row already reached the backend.
     *
     * Any one of the three identifiers proves it did, and re-creating would
     * charge the customer a second time.
     */
    private function wasCreated(VmOrder $order): bool
    {
        return $order->getRemoteVmId() > 0
            || $order->getGroupId() !== ''
            || $order->getRemoteOrderId() !== '';
    }

    /**
     * Give every machine of a bulk creation its own row.
     *
     * A line item ordered with a quantity produces one machine per unit, and a
     * customer has to be able to manage each of them. The first machine stays
     * on the original row; the rest are copied onto new rows that share the
     * WooCommerce item and split its billing amounts, so the order total is
     * unchanged.
     */
    private function splitBulkMachines(VmOrder $order, ProvisioningResult $result): void
    {
        $machines = $result->data()['machines'] ?? [];

        if (!is_array($machines) || count($machines) < 2) {
            return;
        }

        $total = count($machines);
        $now = gmdate('Y-m-d H:i:s');
        $shares = $this->splitAmounts($order, $total);

        $this->orders->update($order->id(), $shares[0]);

        foreach (array_slice($machines, 1) as $index => $machine) {
            if (!$machine instanceof CreatedMachine) {
                continue;
            }

            $this->orders->insert(
                array_merge(
                    $this->siblingColumns($order),
                    $machine->toColumns(),
                    $shares[$index + 1],
                    [
                        'status' => VmOrder::STATUS_PROVISIONING,
                        'provisioning_status' => VmOrder::PROVISIONING_BUILDING,
                        'building_since' => $now,
                        'next_retry_at' => gmdate('Y-m-d H:i:s', time() + self::BUILD_POLL_DELAY),
                    ]
                )
            );
        }

        $this->logger->info(
            sprintf('Order %d created %d machines, split into one row each.', $order->getWcOrderId(), $total),
            [
                'channel' => LogEntry::CHANNEL_PROVISIONING,
                'vm_order_id' => $order->id(),
                'wc_order_id' => $order->getWcOrderId(),
            ]
        );
    }

    /**
     * The order fields a sibling row inherits.
     *
     * @return array<string, mixed>
     */
    private function siblingColumns(VmOrder $order): array
    {
        return [
            'wc_order_id' => $order->getWcOrderId(),
            'wc_order_item_id' => $order->getWcOrderItemId(),
            'product_id' => $order->getProductId(),
            'user_id' => $order->getUserId(),
            'provider_id' => $order->getProviderId(),
            'remote_order_id' => $order->getRemoteOrderId(),
            'remote_payment_id' => $order->getRemotePaymentId(),
            'group_id' => $order->getGroupId(),
            'zone_remote_id' => $order->getZoneRemoteId(),
            'iso_remote_id' => $order->getIsoRemoteId(),
            'plan_type' => $order->getPlanType(),
            'cpu_price_id' => $order->getCpuPriceId(),
            'ram_price_id' => $order->getRamPriceId(),
            'disk_price_id' => $order->getDiskPriceId(),
            'bandwidth_price_id' => $order->getBandwidthPriceId(),
            'months' => $order->getMonths(),
            'quantity' => 1,
            'billing_cycle' => $order->getBillingCycle(),
            'currency' => $order->getCurrency(),
            'coupon_code' => $order->getCouponCode(),
        ];
    }

    /**
     * Divide the billed amounts across the machines of a bulk creation.
     *
     * The remainder of an uneven division stays on the first row so the rows
     * still add up to exactly what the customer was charged.
     *
     * @return array<int, array<string, mixed>>
     */
    private function splitAmounts(VmOrder $order, int $total): array
    {
        $sale = (int) round($order->getSaleAmount() * 100);
        $cost = (int) round($order->getProviderAmount() * 100);

        $saleShare = intdiv($sale, $total);
        $costShare = intdiv($cost, $total);

        $shares = [
            [
                'quantity' => 1,
                'sale_amount' => ($saleShare + $sale - $saleShare * $total) / 100,
                'provider_amount' => ($costShare + $cost - $costShare * $total) / 100,
            ],
        ];

        for ($index = 1; $index < $total; ++$index) {
            $shares[] = [
                'sale_amount' => $saleShare / 100,
                'provider_amount' => $costShare / 100,
            ];
        }

        return $shares;
    }

    /**
     * Store the outcome of an attempt and schedule the next one when needed.
     */
    private function record(VmOrder $order, ProvisioningResult $result): void
    {
        if ($result->isCompleted()) {
            return;
        }

        if ($result->isBuilding()) {
            $this->recordBuilding($order, $result);

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
     * Keep waiting on a machine the backend confirmed it created.
     *
     * The customer has already been charged, so this is never counted against
     * the creation retry budget and never marks the row failed while the wait
     * is within the documented build time. Only a machine that has been
     * starting up far longer than it should is escalated, and even then the
     * identifiers stay on the row so support can find it.
     */
    private function recordBuilding(VmOrder $order, ProvisioningResult $result): void
    {
        $since = $order->getBuildingSince();
        $waited = $order->buildingFor();

        if ($since !== '' && $waited >= self::BUILD_TIMEOUT) {
            $this->orders->update(
                $order->id(),
                [
                    'status' => VmOrder::STATUS_FAILED,
                    'provisioning_status' => VmOrder::PROVISIONING_FAILED,
                    'next_retry_at' => null,
                    'error_message' => __(
                        'The machine was created but never finished starting up. Check it at the provider.',
                        'cloud-vm-manager'
                    ),
                ]
            );

            $this->logger->error(
                sprintf(
                    'Machine %d of order %d has been starting up for %d seconds.',
                    $order->getRemoteVmId(),
                    $order->getWcOrderId(),
                    $waited
                ),
                [
                    'channel' => LogEntry::CHANNEL_PROVISIONING,
                    'vm_order_id' => $order->id(),
                    'wc_order_id' => $order->getWcOrderId(),
                ]
            );

            do_action('cloud_vm_manager_provisioning_failed', $order, $result->message());

            return;
        }

        $this->orders->update(
            $order->id(),
            [
                'status' => VmOrder::STATUS_PROVISIONING,
                'provisioning_status' => VmOrder::PROVISIONING_BUILDING,
                'building_since' => $since !== '' ? $since : gmdate('Y-m-d H:i:s'),
                'last_attempt_at' => gmdate('Y-m-d H:i:s'),
                'next_retry_at' => gmdate('Y-m-d H:i:s', time() + self::BUILD_POLL_DELAY),
                'error_message' => '',
            ]
        );

        $this->scheduleBuildCheck($order->id());
    }

    /**
     * Ask for one check of this machine shortly after it was created.
     *
     * The recurring job alone would leave a machine that builds in a minute
     * waiting for the next five minute tick, so a single event is queued for
     * the moment the machine is expected to answer. It is a plain one-off, so
     * nothing accumulates if it fires late or not at all.
     */
    private function scheduleBuildCheck(int $orderId): void
    {
        $due = time() + self::BUILD_POLL_DELAY;

        if (wp_next_scheduled(self::BUILD_CHECK_HOOK, [$orderId]) !== false) {
            return;
        }

        wp_schedule_single_event($due, self::BUILD_CHECK_HOOK, [$orderId]);
    }

    /**
     * Run one check for a single machine, used by the scheduled build check.
     */
    public function refresh(int $orderId): ?ProvisioningResult
    {
        $order = $this->orders->findOrder($orderId);

        if ($order === null || $order->isProvisioned() || $order->isTerminated()) {
            return null;
        }

        return $this->provision($order);
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
