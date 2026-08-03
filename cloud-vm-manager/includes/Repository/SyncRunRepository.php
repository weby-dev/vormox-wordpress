<?php

/**
 * Sync run repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\SyncRun;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for catalogue synchronisation runs.
 */
final class SyncRunRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::SYNC_RUNS;
    }

    protected function modelClass(): string
    {
        return SyncRun::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'provider_id' => '%d',
            'resource' => '%s',
            'status' => '%s',
            'added' => '%d',
            'updated' => '%d',
            'removed' => '%d',
            'unchanged' => '%d',
            'message' => '%s',
            'context' => '%s',
            'duration_ms' => '%d',
            'started_at' => '%s',
            'finished_at' => '%s',
        ];
    }

    /**
     * The run table carries its own start and finish timestamps.
     */
    protected function hasTimestamps(): bool
    {
        return false;
    }

    /**
     * Open a new run and return its identifier.
     */
    public function start(int $providerId, string $resource): int
    {
        return $this->insert(
            [
                'provider_id' => $providerId,
                'resource' => $resource,
                'status' => SyncRun::STATUS_RUNNING,
                'started_at' => $this->now(),
            ]
        );
    }

    /**
     * Close a run with its result counters.
     *
     * @param array<string, int>   $counters Keys: added, updated, removed, unchanged.
     * @param array<string, mixed> $context  Additional diagnostic data.
     */
    public function finish(
        int $runId,
        string $status,
        array $counters = [],
        string $message = '',
        array $context = []
    ): void {
        $run = $this->find($runId);
        $startedAt = $run instanceof SyncRun ? $run->getDateTime('started_at') : null;
        $duration = $startedAt !== null ? (time() - $startedAt->getTimestamp()) * 1000 : 0;

        $this->update(
            $runId,
            [
                'status' => $status,
                'added' => (int) ($counters['added'] ?? 0),
                'updated' => (int) ($counters['updated'] ?? 0),
                'removed' => (int) ($counters['removed'] ?? 0),
                'unchanged' => (int) ($counters['unchanged'] ?? 0),
                'message' => $message,
                'context' => $context === [] ? null : $context,
                'duration_ms' => max(0, $duration),
                'finished_at' => $this->now(),
            ]
        );
    }

    /**
     * Most recent runs, newest first.
     *
     * @return SyncRun[]
     */
    public function latest(int $limit = 10, int $providerId = 0): array
    {
        $conditions = $providerId > 0 ? ['provider_id' => $providerId] : [];

        /** @var SyncRun[] $runs */
        $runs = $this->findBy($conditions, ['order_by' => 'id', 'order' => 'DESC', 'limit' => $limit]);

        return $runs;
    }

    /**
     * Latest run of a provider, regardless of the resource.
     */
    public function latestForProvider(int $providerId): ?SyncRun
    {
        $runs = $this->latest(1, $providerId);

        return $runs === [] ? null : $runs[0];
    }

    /**
     * Delete runs older than the retention period.
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
                'started_at' => ['operator' => '<', 'value' => $cutoff],
            ]
        );
    }
}
