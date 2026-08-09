<?php

/**
 * VM order repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\VmOrder;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for sold virtual machines.
 */
final class VmOrderRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::VM_ORDERS;
    }

    protected function modelClass(): string
    {
        return VmOrder::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'wc_order_id' => '%d',
            'wc_order_item_id' => '%d',
            'product_id' => '%d',
            'user_id' => '%d',
            'provider_id' => '%d',
            'remote_user_id' => '%d',
            'remote_vm_id' => '%d',
            'proxmox_vmid' => '%d',
            'remote_order_id' => '%s',
            'remote_payment_id' => '%s',
            'group_id' => '%s',
            'zone_remote_id' => '%d',
            'iso_remote_id' => '%d',
            'plan_type' => '%s',
            'cpu_price_id' => '%d',
            'ram_price_id' => '%d',
            'disk_price_id' => '%d',
            'bandwidth_price_id' => '%d',
            'months' => '%d',
            'quantity' => '%d',
            'billing_cycle' => '%s',
            'hostname' => '%s',
            'ip_address' => '%s',
            'os_name' => '%s',
            'cpu_cores' => '%d',
            'ram_mb' => '%d',
            'disk_gb' => '%d',
            'bandwidth_gb' => '%d',
            'status' => '%s',
            'provisioning_status' => '%s',
            'attempts' => '%d',
            'building_since' => '%s',
            'last_attempt_at' => '%s',
            'next_retry_at' => '%s',
            'currency' => '%s',
            'provider_amount' => '%f',
            'sale_amount' => '%f',
            'coupon_code' => '%s',
            'error_message' => '%s',
            'meta' => '%s',
            'provisioned_at' => '%s',
            'renews_at' => '%s',
            'terminated_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    public function findOrder(int $id): ?VmOrder
    {
        $order = $this->find($id);

        return $order instanceof VmOrder ? $order : null;
    }

    /**
     * Machines belonging to a WooCommerce order.
     *
     * @return VmOrder[]
     */
    public function forWooCommerceOrder(int $wcOrderId): array
    {
        /** @var VmOrder[] $orders */
        $orders = $this->findBy(['wc_order_id' => $wcOrderId], ['order_by' => 'id', 'order' => 'ASC']);

        return $orders;
    }

    /**
     * Machines belonging to a customer.
     *
     * @param string[] $statuses Optional status filter.
     *
     * @return VmOrder[]
     */
    public function forUser(int $userId, array $statuses = []): array
    {
        $conditions = ['user_id' => $userId];

        if ($statuses !== []) {
            $conditions['status'] = $statuses;
        }

        /** @var VmOrder[] $orders */
        $orders = $this->findBy($conditions, ['order_by' => 'created_at', 'order' => 'DESC']);

        return $orders;
    }

    /**
     * Paginated machines of a customer.
     *
     * @param array<string, mixed> $args
     *
     * @return array{items: VmOrder[], total: int, page: int, per_page: int, pages: int}
     */
    public function paginateForUser(int $userId, array $args = []): array
    {
        return $this->paginate(['user_id' => $userId], $args + ['order_by' => 'created_at', 'order' => 'DESC']);
    }

    /**
     * Look a machine up by its backend identifier.
     */
    public function findByRemoteVmId(int $remoteVmId): ?VmOrder
    {
        $order = $this->findOneBy(['remote_vm_id' => $remoteVmId]);

        return $order instanceof VmOrder ? $order : null;
    }

    /**
     * Whether the customer owns the machine.
     */
    public function isOwnedBy(int $vmOrderId, int $userId): bool
    {
        return $this->exists(['id' => $vmOrderId, 'user_id' => $userId]);
    }

    /**
     * Machines whose provisioning is due for another attempt.
     *
     * @return VmOrder[]
     */
    public function dueForRetry(int $limit = 10): array
    {
        $sql = 'SELECT * FROM `' . $this->table() . '`'
            . ' WHERE provisioning_status IN (%s, %s, %s)'
            . ' AND (next_retry_at IS NULL OR next_retry_at <= %s)'
            . ' ORDER BY next_retry_at ASC LIMIT %d';

        $rows = $this->results(
            $sql,
            [
                VmOrder::PROVISIONING_RETRYING,
                VmOrder::PROVISIONING_QUEUED,
                VmOrder::PROVISIONING_BUILDING,
                $this->now(),
                max(1, $limit),
            ]
        );

        /** @var VmOrder[] $orders */
        $orders = array_map([$this, 'hydrate'], $rows);

        return $orders;
    }

    /**
     * Number of machines per status.
     *
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $sql = 'SELECT status, COUNT(*) AS total FROM `' . $this->table() . '` GROUP BY status';
        $counts = [];

        foreach ($this->results($sql) as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        return $counts;
    }

    /**
     * Total revenue of provisioned machines.
     */
    public function totalRevenue(): float
    {
        $sql = 'SELECT SUM(sale_amount) FROM `' . $this->table() . '` WHERE status != %s';

        return (float) $this->scalar($sql, [VmOrder::STATUS_FAILED]);
    }

    /**
     * Number of distinct customers owning at least one machine.
     */
    public function countCustomers(): int
    {
        $sql = 'SELECT COUNT(DISTINCT user_id) FROM `' . $this->table() . '` WHERE user_id > 0';

        return (int) $this->scalar($sql);
    }

    /**
     * Record a provisioning attempt.
     */
    public function registerAttempt(
        int $vmOrderId,
        string $provisioningStatus,
        string $error = '',
        int $retryDelay = 0
    ): void {
        $order = $this->findOrder($vmOrderId);

        if ($order === null) {
            return;
        }

        $data = [
            'attempts' => $order->getAttempts() + 1,
            'provisioning_status' => $provisioningStatus,
            'last_attempt_at' => $this->now(),
            'error_message' => $error,
        ];

        $data['next_retry_at'] = $retryDelay > 0
            ? gmdate('Y-m-d H:i:s', time() + $retryDelay)
            : null;

        $this->update($vmOrderId, $data);
    }
}
