<?php

/**
 * Account synchronisation AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderService;
use CloudVmManager\Service\Vm\AccountSyncService;
use CloudVmManager\Service\Vm\UsageService;

defined('ABSPATH') || exit;

/**
 * Re-reads machines from the backend on demand, from the admin machine list.
 */
final class AccountSyncAjaxController extends AbstractAjaxController
{
    public const ACTION_SYNC_MACHINE = 'cvm_sync_machine';
    public const ACTION_SYNC_PROVIDER = 'cvm_sync_account';
    public const ACTION_REFRESH_USAGE = 'cvm_refresh_usage';

    /**
     * @var AccountSyncService
     */
    private $accounts;

    /**
     * @var UsageService
     */
    private $usage;

    /**
     * @var ProviderService
     */
    private $providers;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    public function __construct(
        AccountSyncService $accounts,
        UsageService $usage,
        ProviderService $providers,
        VmOrderRepository $orders
    ) {
        $this->accounts = $accounts;
        $this->usage = $usage;
        $this->providers = $providers;
        $this->orders = $orders;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_SYNC_MACHINE, [$this, 'syncMachine']);
        add_action('wp_ajax_' . self::ACTION_SYNC_PROVIDER, [$this, 'syncProvider']);
        add_action('wp_ajax_' . self::ACTION_REFRESH_USAGE, [$this, 'refreshUsage']);
    }

    /**
     * Re-read one machine.
     */
    public function syncMachine(): void
    {
        $this->authorize(Access::AJAX_NONCE, Access::capability());

        $machine = $this->requireMachine();

        if (!$machine instanceof VmOrder) {
            return;
        }

        $result = $this->accounts->syncMachine($machine);

        if (!$result->isSuccessful()) {
            $this->failure($result->summary(), 200, ['result' => $result->toArray()]);

            return;
        }

        $this->success(
            [
                'message' => $result->summary(),
                'result' => $result->toArray(),
                'machine' => $this->machinePayload($machine->id()),
            ]
        );
    }

    /**
     * Reconcile every machine of one provider.
     */
    public function syncProvider(): void
    {
        $this->authorize(Access::AJAX_NONCE, Access::capability());

        $provider = $this->providers->find($this->intParam('provider_id'));

        if (!$provider instanceof Provider) {
            $this->failure(__('That provider no longer exists.', 'cloud-vm-manager'), 404);

            return;
        }

        $result = $this->accounts->syncProvider($provider);

        if (!$result->isSuccessful()) {
            $this->failure($result->summary(), 200, ['result' => $result->toArray()]);

            return;
        }

        $this->success(['message' => $result->summary(), 'result' => $result->toArray()]);
    }

    /**
     * Read the usage figures of one machine now.
     */
    public function refreshUsage(): void
    {
        $this->authorize(Access::AJAX_NONCE, Access::capability());

        $machine = $this->requireMachine();

        if (!$machine instanceof VmOrder) {
            return;
        }

        $usage = $this->usage->refresh($machine);

        if ($usage === null) {
            $this->failure(__('This machine cannot report its usage yet.', 'cloud-vm-manager'));

            return;
        }

        $this->success(
            [
                'message' => __('Usage updated.', 'cloud-vm-manager'),
                'machine' => $this->machinePayload($machine->id()),
            ]
        );
    }

    /**
     * The machine named by the request, answering the client when there is none.
     */
    private function requireMachine(): ?VmOrder
    {
        $machine = $this->orders->findOrder($this->intParam('machine_id'));

        if (!$machine instanceof VmOrder) {
            $this->failure(__('That machine no longer exists.', 'cloud-vm-manager'), 404);

            return null;
        }

        return $machine;
    }

    /**
     * Fields the machine row shows, read back after a change.
     *
     * @return array<string, mixed>
     */
    private function machinePayload(int $id): array
    {
        $machine = $this->orders->findOrder($id);

        if (!$machine instanceof VmOrder) {
            return [];
        }

        return [
            'id' => $machine->id(),
            'hostname' => $machine->getHostname(),
            'ip_address' => $machine->getIpAddress(),
            'os_name' => $machine->getOsName(),
            'status' => $machine->getStatus(),
            'disk_used_mb' => $machine->getDiskUsedMb(),
            'bandwidth_used_mb' => $machine->getBandwidthUsedMb(),
            'usage_updated_at' => $machine->getUsageUpdatedAt(),
        ];
    }
}
