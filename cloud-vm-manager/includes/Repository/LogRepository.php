<?php

/**
 * Log repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\LogEntry;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for the plugin log.
 */
final class LogRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::VM_LOGS;
    }

    protected function modelClass(): string
    {
        return LogEntry::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'level' => '%s',
            'channel' => '%s',
            'message' => '%s',
            'context' => '%s',
            'provider_id' => '%d',
            'vm_order_id' => '%d',
            'wc_order_id' => '%d',
            'user_id' => '%d',
            'method' => '%s',
            'endpoint' => '%s',
            'status_code' => '%d',
            'duration_ms' => '%d',
            'ip_address' => '%s',
            'created_at' => '%s',
        ];
    }

    /**
     * The log table only carries a creation timestamp.
     */
    protected function hasTimestamps(): bool
    {
        return false;
    }

    /**
     * Most recent entries, newest first.
     *
     * @param array<string, mixed> $conditions
     *
     * @return LogEntry[]
     */
    public function latest(int $limit = 20, array $conditions = []): array
    {
        /** @var LogEntry[] $entries */
        $entries = $this->findBy(
            $conditions,
            [
                'order_by' => 'id',
                'order' => 'DESC',
                'limit' => $limit,
            ]
        );

        return $entries;
    }

    /**
     * Activity of a single customer.
     *
     * @return LogEntry[]
     */
    public function forUser(int $userId, int $limit = 20): array
    {
        return $this->latest($limit, ['user_id' => $userId]);
    }

    /**
     * Activity recorded for a single machine.
     *
     * @return LogEntry[]
     */
    public function forVmOrder(int $vmOrderId, int $limit = 20): array
    {
        return $this->latest($limit, ['vm_order_id' => $vmOrderId]);
    }

    /**
     * Delete entries older than the retention period.
     *
     * @return int Number of deleted rows.
     */
    public function purgeOlderThan(int $days): int
    {
        if ($days < 1) {
            return 0;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        return $this->deleteWhere(
            [
                'created_at' => ['operator' => '<', 'value' => $cutoff],
            ]
        );
    }

    /**
     * Number of entries per level.
     *
     * @return array<string, int>
     */
    public function countByLevel(): array
    {
        $sql = 'SELECT level, COUNT(*) AS total FROM `' . $this->table() . '` GROUP BY level';
        $counts = [];

        foreach ($this->results($sql) as $row) {
            $counts[(string) $row['level']] = (int) $row['total'];
        }

        return $counts;
    }
}
