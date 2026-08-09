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
 * Reads back the machine that a creation request produced.
 *
 * When the creation response named the machine, its identifier is already
 * stored and the detail endpoint is asked directly. That call is also the
 * readiness test: a machine takes up to a minute to build, and until it answers
 * its own detail endpoint there is nothing to promote.
 *
 * The overview scan is the fallback for the case where no machine was named,
 * matching on the payment, order and group identifiers instead.
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
     * @return array<string, mixed> Empty while the machine is still building or
     *                              is not listed by the backend yet.
     */
    public function resolve(Provider $provider, VmOrder $order): array
    {
        $vmId = $order->getRemoteVmId();

        if ($vmId > 0) {
            $details = $this->fetchDetails($provider, $vmId);

            return $details === null ? [] : array_merge(['remote_vm_id' => $vmId], $this->fields($details));
        }

        $entry = $this->findInOverview($provider, $order);

        if ($entry === []) {
            return [];
        }

        $vmId = $this->identifier($entry);

        if ($vmId <= 0) {
            return [];
        }

        $details = $this->fetchDetails($provider, $vmId);

        return array_merge(
            ['remote_vm_id' => $vmId],
            $this->fields($details === null ? $entry : array_merge($entry, $details))
        );
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
     * Read the machine detail record.
     *
     * @return array<string, mixed>|null Null while the machine is not answering
     *                                   yet, which is the normal state for the
     *                                   first minute of its life.
     */
    private function fetchDetails(Provider $provider, int $vmId): ?array
    {
        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::orderDetails($vmId)),
                ['channel' => LogEntry::CHANNEL_PROVISIONING]
            );
        } catch (ApiException $exception) {
            return null;
        }

        return $response->isSuccessful() ? $response->data() : null;
    }

    /**
     * Map a machine payload onto the stored columns.
     *
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function fields(array $payload): array
    {
        $proxmoxVmid = Arr::first($payload, ['proxmoxVmid', 'vmid'], 0);
        $remoteUserId = Arr::first($payload, ['userId', 'ownerId'], 0);

        $fields = [
            'proxmox_vmid' => is_numeric($proxmoxVmid) ? (int) $proxmoxVmid : 0,
            'remote_user_id' => is_numeric($remoteUserId) ? (int) $remoteUserId : 0,
            'hostname' => (string) Arr::first($payload, ['vmName', 'serverName', 'name', 'hostname'], ''),
            'ip_address' => (string) Arr::first($payload, ['vmip', 'ipAddress', 'ip', 'vmIp'], ''),
            'os_name' => (string) Arr::first($payload, ['osName', 'isoName', 'osType'], ''),
        ];

        /*
         * The creation response already supplied the name, the address and the
         * hypervisor identifier. A detail record that omits one of them must
         * not blank what is already known, so empty values are dropped rather
         * than written.
         */
        $fields = array_filter(
            $fields,
            static function ($value) {
                return $value !== '' && $value !== 0;
            }
        );

        $fields['meta'] = $payload;

        return $fields;
    }
}
