<?php

/**
 * Machine control actions.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\ApiResponse;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;
use CloudVmManager\Support\Cache;

defined('ABSPATH') || exit;

/**
 * Everything a customer can do to a machine.
 *
 * Each action checks the lock state first, because the backend refuses a
 * suspended machine and a refusal reads better as a clear message than as an
 * error. Every action is logged against the machine and the customer.
 */
final class VmControlService
{
    /**
     * Power actions the control endpoint documents.
     *
     * @var string[]
     */
    public const POWER_ACTIONS = ['start', 'stop', 'reboot', 'pause', 'hibernate', 'resume'];

    /**
     * Minimum length a customer supplied machine password may have.
     */
    private const MIN_PASSWORD_LENGTH = 8;

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var IsoTemplateRepository
     */
    private $isos;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProviderGateway $gateway,
        VmService $machines,
        IsoTemplateRepository $isos,
        VmOrderRepository $orders,
        Cache $cache,
        LoggerInterface $logger
    ) {
        $this->gateway = $gateway;
        $this->machines = $machines;
        $this->isos = $isos;
        $this->orders = $orders;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Whether an action name is one the backend documents.
     */
    public function isPowerAction(string $action): bool
    {
        return in_array(strtolower(trim($action)), self::POWER_ACTIONS, true);
    }

    /**
     * Start, stop, reboot, pause, hibernate or resume a machine.
     */
    public function power(VmOrder $order, string $action): ControlResult
    {
        $action = strtolower(trim($action));

        if (!$this->isPowerAction($action)) {
            return ControlResult::failure(__('That power action is not supported.', 'cloud-vm-manager'));
        }

        $guard = $this->guard($order);

        if ($guard !== null) {
            return $guard;
        }

        return $this->dispatch(
            $order,
            ApiRequest::post(
                Endpoints::vmControl($order->getRemoteUserId(), $order->getRemoteVmId()),
                [],
                ['action' => $action]
            ),
            sprintf('power:%s', $action),
            __('The action was sent to the machine.', 'cloud-vm-manager')
        );
    }

    /**
     * Reinstall the operating system of a machine.
     *
     * @param int $isoRemoteId Backend image identifier to install.
     */
    public function rebuild(VmOrder $order, int $isoRemoteId): ControlResult
    {
        $guard = $this->guard($order);

        if ($guard !== null) {
            return $guard;
        }

        if (!$this->isoBelongsToZone($order, $isoRemoteId)) {
            return ControlResult::failure(
                __('That operating system is not available in this zone.', 'cloud-vm-manager')
            );
        }

        $result = $this->dispatch(
            $order,
            ApiRequest::post(
                Endpoints::vmRebuild($order->getRemoteUserId(), $order->getRemoteVmId()),
                [],
                ['isoId' => $isoRemoteId]
            ),
            'rebuild',
            __('The rebuild has started. This takes a few minutes.', 'cloud-vm-manager')
        );

        if ($result->isSuccessful()) {
            $template = $this->isos->findByRemoteId(
                $order->getProviderId(),
                $order->getZoneRemoteId(),
                $isoRemoteId
            );

            $this->orders->update(
                $order->id(),
                [
                    'iso_remote_id' => $isoRemoteId,
                    'os_name' => $template !== null ? $template->getIsoName() : $order->getOsName(),
                ]
            );
        }

        return $result;
    }

    /**
     * Change the administrator password of a machine.
     */
    public function changePassword(VmOrder $order, string $password): ControlResult
    {
        $guard = $this->guard($order);

        if ($guard !== null) {
            return $guard;
        }

        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return ControlResult::failure(
                sprintf(
                    /* translators: %d: minimum number of characters. */
                    __('The password must be at least %d characters long.', 'cloud-vm-manager'),
                    self::MIN_PASSWORD_LENGTH
                )
            );
        }

        return $this->dispatch(
            $order,
            ApiRequest::put(
                Endpoints::vmPassword($order->getRemoteUserId(), $order->getRemoteVmId()),
                ['password' => $password]
            ),
            'password',
            __('The password was updated.', 'cloud-vm-manager')
        );
    }

    /**
     * Give the machine a new MAC address.
     */
    public function regenerateMac(VmOrder $order): ControlResult
    {
        $guard = $this->guard($order);

        if ($guard !== null) {
            return $guard;
        }

        return $this->dispatch(
            $order,
            ApiRequest::post(Endpoints::vmRegenerateMac($order->getRemoteVmId())),
            'mac',
            __('A new MAC address was generated. The machine restarts to apply it.', 'cloud-vm-manager')
        );
    }

    /**
     * Re-apply the guest network configuration.
     */
    public function reconfigureNetwork(VmOrder $order): ControlResult
    {
        $guard = $this->guard($order);

        if ($guard !== null) {
            return $guard;
        }

        return $this->dispatch(
            $order,
            ApiRequest::post(Endpoints::vmReconfigureNetwork($order->getRemoteVmId())),
            'network',
            __('The network configuration is being re-applied. The machine restarts shortly.', 'cloud-vm-manager')
        );
    }

    /**
     * Images that can be installed on a machine.
     *
     * @return array<int, string>
     */
    public function rebuildOptions(VmOrder $order): array
    {
        $options = [];

        foreach ($this->isos->forZone($order->getProviderId(), $order->getZoneRemoteId()) as $iso) {
            $options[$iso->getRemoteId()] = $iso->getIsoName();
        }

        return $options;
    }

    /**
     * Refuse the action when the machine cannot accept one.
     */
    private function guard(VmOrder $order): ?ControlResult
    {
        if (!$order->isProvisioned()) {
            return ControlResult::failure(__('This machine is not ready yet.', 'cloud-vm-manager'));
        }

        if ($order->isTerminated()) {
            return ControlResult::failure(__('This machine has been terminated.', 'cloud-vm-manager'));
        }

        $lock = $this->machines->lockStatus($order);

        if ($lock['locked']) {
            return ControlResult::failure(
                $lock['message'] !== ''
                    ? $lock['message']
                    : __('This machine is locked. Please contact support.', 'cloud-vm-manager')
            );
        }

        return null;
    }

    /**
     * Whether the image is offered in the zone the machine runs in.
     */
    private function isoBelongsToZone(VmOrder $order, int $isoRemoteId): bool
    {
        if ($isoRemoteId <= 0) {
            return false;
        }

        return $this->isos->findByRemoteId(
            $order->getProviderId(),
            $order->getZoneRemoteId(),
            $isoRemoteId
        ) !== null;
    }

    /**
     * Send a control request and turn its answer into a result.
     */
    private function dispatch(
        VmOrder $order,
        ApiRequest $request,
        string $action,
        string $successMessage
    ): ControlResult {
        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            return ControlResult::failure(__('The provider of this machine is unavailable.', 'cloud-vm-manager'));
        }

        try {
            $response = $this->gateway->request(
                $provider,
                $request,
                [
                    'channel' => LogEntry::CHANNEL_CUSTOMER,
                    'vm_order_id' => $order->id(),
                    'user_id' => $order->getUserId(),
                    'vm_action' => $action,
                ]
            );
        } catch (ApiException $exception) {
            $this->log($order, $action, false, $exception->getMessage());

            return ControlResult::failure($exception->getMessage());
        }

        if (!$response->isSuccessful()) {
            $this->log($order, $action, false, $response->errorMessage());

            return ControlResult::failure($response->errorMessage());
        }

        $this->cache->flush($provider->id());
        $this->log($order, $action, true, $successMessage);

        /**
         * Fires after a control action was accepted by the backend.
         *
         * @param VmOrder $order  Machine the action ran on.
         * @param string  $action Action identifier.
         */
        do_action('cloud_vm_manager_vm_action', $order, $action);

        return ControlResult::success($this->message($response, $successMessage), $this->payload($response));
    }

    /**
     * Prefer the message the backend returned.
     */
    private function message(ApiResponse $response, string $fallback): string
    {
        $message = Arr::get($response->data(), 'message');

        return is_string($message) && $message !== '' ? $message : $fallback;
    }

    /**
     * Fields worth handing back to the interface.
     *
     * @return array<string, mixed>
     */
    private function payload(ApiResponse $response): array
    {
        $data = $response->data();

        $payload = [];

        foreach (['task_id', 'status', 'newMac'] as $key) {
            $value = Arr::get($data, $key);

            if (is_scalar($value)) {
                $payload[$key] = (string) $value;
            }
        }

        return $payload;
    }

    /**
     * Record the action in the plugin activity log.
     */
    private function log(VmOrder $order, string $action, bool $successful, string $message): void
    {
        $context = [
            'channel' => LogEntry::CHANNEL_CUSTOMER,
            'vm_order_id' => $order->id(),
            'user_id' => $order->getUserId(),
            'provider_id' => $order->getProviderId(),
            'vm_action' => $action,
        ];

        $summary = sprintf(
            '%s on machine %s: %s',
            $action,
            $order->getHostname() !== '' ? $order->getHostname() : (string) $order->getRemoteVmId(),
            $message
        );

        if ($successful) {
            $this->logger->info($summary, $context);

            return;
        }

        $this->logger->warning($summary, $context);
    }
}
