<?php

/**
 * Created machine resolution.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provisioning;

use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;

defined('ABSPATH') || exit;

/**
 * Finds the machine that a creation request produced.
 *
 * The create endpoint answers with the payment, order and group identifiers but
 * not with the machine identifier, so the order overview is read back and
 * matched on those identifiers. The detail endpoint then fills in the
 * hypervisor identifier, the address and the name.
 */
final class VmResolver
{
    /**
     * How many overview entries are scanned when matching.
     */
    private const PAGE_SIZE = 25;

    /**
     * @var ProviderGateway
     */
    private $gateway;

    public function __construct(ProviderGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Locate the machine belonging to an order and return the fields to store.
     *
     * @return array<string, mixed> Empty when the machine is not listed yet.
     */
    public function resolve(Provider $provider, VmOrder $order): array
    {
        $entry = $this->findInOverview($provider, $order);

        if ($entry === []) {
            return [];
        }

        $vmId = $this->identifier($entry);

        if ($vmId <= 0) {
            return [];
        }

        return array_merge(['remote_vm_id' => $vmId], $this->details($provider, $vmId, $entry));
    }

    /**
     * Scan the order overview for the entry matching the stored identifiers.
     *
     * @return array<string, mixed>
     */
    private function findInOverview(Provider $provider, VmOrder $order): array
    {
        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(
                    Endpoints::ORDERS_OVERVIEW,
                    [
                        'page' => 0,
                        'size' => self::PAGE_SIZE,
                        'sortBy' => 'createdAt',
                        'sortDir' => 'desc',
                    ]
                ),
                ['channel' => LogEntry::CHANNEL_PROVISIONING, 'vm_order_id' => $order->id()]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            return [];
        }

        foreach ($this->entries($response->data()) as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if ($this->matches($entry, $order)) {
                return $entry;
            }
        }

        return [];
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

    /**
     * Whether an overview entry belongs to the given order.
     *
     * @param array<string, mixed> $entry
     */
    private function matches(array $entry, VmOrder $order): bool
    {
        $groupId = $order->getGroupId();

        if ($groupId !== '' && (string) Arr::first($entry, ['groupId', 'bulkGroupId'], '') === $groupId) {
            return true;
        }

        $remoteOrderId = $order->getRemoteOrderId();

        if ($remoteOrderId !== '' && (string) Arr::first($entry, ['orderId', 'orderRef'], '') === $remoteOrderId) {
            return true;
        }

        $paymentId = $order->getRemotePaymentId();

        return $paymentId !== '' && (string) Arr::first($entry, ['paymentId'], '') === $paymentId;
    }

    /**
     * Internal machine identifier of an overview entry.
     *
     * @param array<string, mixed> $entry
     */
    private function identifier(array $entry): int
    {
        $value = Arr::first($entry, ['vmId', 'virtualMachineId', 'internalvmid', 'id'], 0);

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Read the machine detail record, falling back to the overview entry.
     *
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>
     */
    private function details(Provider $provider, int $vmId, array $entry): array
    {
        $payload = $entry;

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::orderDetails($vmId)),
                ['channel' => LogEntry::CHANNEL_PROVISIONING]
            );

            if ($response->isSuccessful()) {
                $payload = array_merge($entry, $response->data());
            }
        } catch (ApiException $exception) {
            /* The overview entry is enough to record the machine. */
        }

        $proxmoxVmid = Arr::first($payload, ['proxmoxVmid', 'vmid'], 0);
        $remoteUserId = Arr::first($payload, ['userId', 'ownerId'], 0);

        return [
            'proxmox_vmid' => is_numeric($proxmoxVmid) ? (int) $proxmoxVmid : 0,
            'remote_user_id' => is_numeric($remoteUserId) ? (int) $remoteUserId : 0,
            'hostname' => (string) Arr::first($payload, ['vmName', 'serverName', 'name', 'hostname'], ''),
            'ip_address' => (string) Arr::first($payload, ['ipAddress', 'ip', 'vmIp'], ''),
            'os_name' => (string) Arr::first($payload, ['osName', 'isoName', 'osType'], ''),
            'meta' => $payload,
        ];
    }
}
