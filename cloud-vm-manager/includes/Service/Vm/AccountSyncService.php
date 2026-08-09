<?php

/**
 * Account synchronisation.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;

defined('ABSPATH') || exit;

/**
 * Brings the stored machines back in line with the backend.
 *
 * The equivalent of the account synchronisation a hosting module offers: it
 * re-reads what the backend holds and writes it over the local row, so a change
 * made directly at the provider — a rebuild, a rename, a new address — shows up
 * in the store without waiting for anything.
 *
 * A machine the backend knows about but the store does not is imported rather
 * than ignored, because the alternative is a machine nobody can see. An imported
 * machine has no WooCommerce order behind it, which is recorded on the row so it
 * is never mistaken for something that was sold.
 */
final class AccountSyncService
{
    /**
     * Overview entries read in one pass.
     */
    private const PAGE_SIZE = 100;

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var VmService
     */
    private $machines;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProviderGateway $gateway,
        VmOrderRepository $orders,
        VmService $machines,
        LoggerInterface $logger
    ) {
        $this->gateway = $gateway;
        $this->orders = $orders;
        $this->machines = $machines;
        $this->logger = $logger;
    }

    /**
     * Re-read one machine and store what changed.
     */
    public function syncMachine(VmOrder $order): AccountSyncResult
    {
        $result = new AccountSyncResult();

        if ($order->getRemoteVmId() <= 0) {
            $result->recordError(__('This machine has no backend identifier yet.', 'cloud-vm-manager'));

            return $result;
        }

        $provider = $this->machines->providerFor($order);

        if (!$provider instanceof Provider) {
            $result->recordError(__('The provider of this machine is unavailable.', 'cloud-vm-manager'));

            return $result;
        }

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::orderDetails($order->getRemoteVmId())),
                ['channel' => LogEntry::CHANNEL_ADMIN, 'vm_order_id' => $order->id()]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            $result->recordError($exception->getMessage());

            return $result;
        }

        $this->apply($order, $response->data(), $result);

        return $result;
    }

    /**
     * Re-read every machine of one WordPress customer.
     */
    public function syncUser(int $userId): AccountSyncResult
    {
        $result = new AccountSyncResult();

        foreach ($this->machines->allFor($userId) as $machine) {
            $this->merge($result, $this->syncMachine($machine));
        }

        return $result;
    }

    /**
     * Reconcile every machine a provider holds against the stored rows.
     */
    public function syncProvider(Provider $provider): AccountSyncResult
    {
        $result = new AccountSyncResult();

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(
                    Endpoints::ORDERS_OVERVIEW,
                    ['page' => 0, 'size' => self::PAGE_SIZE, 'sortBy' => 'createdAt', 'sortDir' => 'desc']
                ),
                ['channel' => LogEntry::CHANNEL_ADMIN, 'provider_id' => $provider->id()]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            $result->recordError($exception->getMessage());

            return $result;
        }

        foreach ($this->entries($response->data()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $remoteVmId = $this->identifier($entry);

            if ($remoteVmId <= 0) {
                continue;
            }

            $order = $this->orders->findOneBy(
                ['provider_id' => $provider->id(), 'remote_vm_id' => $remoteVmId]
            );

            if ($order instanceof VmOrder) {
                $this->apply($order, $entry, $result);

                continue;
            }

            $this->import($provider, $remoteVmId, $entry, $result);
        }

        $this->logger->info(
            sprintf(
                'Account synchronisation for "%s": %d updated, %d imported.',
                $provider->getName(),
                $result->updated(),
                $result->imported()
            ),
            ['channel' => LogEntry::CHANNEL_ADMIN, 'provider_id' => $provider->id()]
        );

        return $result;
    }

    /**
     * Write the fields a backend payload carries onto a stored row.
     *
     * Only fields the payload actually names are written, so a sparse record
     * never blanks something the store already knows.
     *
     * @param array<string, mixed> $payload
     */
    private function apply(VmOrder $order, array $payload, AccountSyncResult $result): void
    {
        $fields = array_filter(
            [
                'hostname' => (string) Arr::first($payload, ['vmName', 'serverName', 'name', 'hostname'], ''),
                'ip_address' => (string) Arr::first($payload, ['vmip', 'ipAddress', 'ip', 'vmIp'], ''),
                'os_name' => (string) Arr::first($payload, ['osName', 'isoName', 'osType'], ''),
                'proxmox_vmid' => (int) $this->numeric($payload, ['proxmoxVmid', 'vmid']),
                'remote_user_id' => (int) $this->numeric($payload, ['userId', 'ownerId']),
            ],
            static function ($value) {
                return $value !== '' && $value !== 0;
            }
        );

        $changed = [];

        foreach ($fields as $column => $value) {
            if ($order->get($column) !== $value) {
                $changed[$column] = $value;
            }
        }

        if ($changed === []) {
            $result->recordUnchanged();

            return;
        }

        $changed['meta'] = array_merge($order->getMeta(), $payload);

        $this->orders->update($order->id(), $changed);
        $result->recordUpdated();
    }

    /**
     * Store a machine the backend holds that the store had no row for.
     *
     * @param array<string, mixed> $entry
     */
    private function import(Provider $provider, int $remoteVmId, array $entry, AccountSyncResult $result): void
    {
        $proxmoxVmid = (int) $this->numeric($entry, ['proxmoxVmid', 'vmid']);

        $this->orders->insert(
            [
                'provider_id' => $provider->id(),
                'remote_vm_id' => $remoteVmId,
                'proxmox_vmid' => $proxmoxVmid,
                'remote_user_id' => (int) $this->numeric($entry, ['userId', 'ownerId']),
                'remote_order_id' => (string) Arr::first($entry, ['orderId', 'orderRef'], ''),
                'remote_payment_id' => (string) Arr::first($entry, ['paymentId'], ''),
                'group_id' => (string) Arr::first($entry, ['groupId', 'bulkGroupId'], ''),
                'hostname' => (string) Arr::first($entry, ['vmName', 'serverName', 'name', 'hostname'], ''),
                'ip_address' => (string) Arr::first($entry, ['vmip', 'ipAddress', 'ip', 'vmIp'], ''),
                'os_name' => (string) Arr::first($entry, ['osName', 'isoName', 'osType'], ''),
                'status' => VmOrder::STATUS_ACTIVE,
                'provisioning_status' => VmOrder::PROVISIONING_COMPLETED,
                'provisioned_at' => gmdate('Y-m-d H:i:s'),
                /*
                 * No WooCommerce order exists for a machine that was created
                 * outside the store, and the zero order id is what says so.
                 */
                'wc_order_id' => 0,
                'meta' => $entry,
            ]
        );

        $result->recordImported();
    }

    private function merge(AccountSyncResult $into, AccountSyncResult $from): void
    {
        for ($i = 0; $i < $from->updated(); ++$i) {
            $into->recordUpdated();
        }

        for ($i = 0; $i < $from->imported(); ++$i) {
            $into->recordImported();
        }

        for ($i = 0; $i < $from->unchanged(); ++$i) {
            $into->recordUnchanged();
        }

        foreach ($from->errors() as $error) {
            $into->recordError($error);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[]             $keys
     */
    private function numeric(array $payload, array $keys): int
    {
        $value = Arr::first($payload, $keys, 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function identifier(array $entry): int
    {
        return $this->numeric($entry, ['dbVmId', 'vmId', 'virtualMachineId', 'internalvmid', 'id']);
    }

    /**
     * Pull the list out of a paginated or plain response.
     *
     * @param array<string, mixed>|array<int, mixed> $data
     *
     * @return array<int, mixed>
     */
    private function entries(array $data): array
    {
        if (Arr::isList($data)) {
            return $data;
        }

        foreach (['orders', 'content', 'data', 'items', 'results'] as $key) {
            $nested = Arr::get($data, $key);

            if (is_array($nested) && Arr::isList($nested)) {
                return $nested;
            }
        }

        return [];
    }
}
