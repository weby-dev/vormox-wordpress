<?php

/**
 * Maintenance job.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Cron;

use CloudVmManager\Contracts\JobInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Daily housekeeping: expired cache rows, old log entries and old sync runs.
 *
 * Without it the log and cache tables would grow without bound on a busy store.
 */
final class MaintenanceJob implements JobInterface
{
    public const HOOK = 'cvm_maintenance';

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var LogRepository
     */
    private $logs;

    /**
     * @var SyncRunRepository
     */
    private $syncRuns;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Cache $cache,
        LogRepository $logs,
        SyncRunRepository $syncRuns,
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->cache = $cache;
        $this->logs = $logs;
        $this->syncRuns = $syncRuns;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function hook(): string
    {
        return self::HOOK;
    }

    public function recurrence(): string
    {
        return 'daily';
    }

    public function run(): void
    {
        $expiredCache = $this->cache->purgeExpired();
        $purgedLogs = $this->logs->purgeOlderThan($this->settings->getInt('log_retention_days', 30));
        $purgedRuns = $this->syncRuns->purgeOlderThan($this->settings->getInt('sync_retention_days', 30));

        $this->logger->info(
            'Maintenance completed.',
            [
                'channel' => LogEntry::CHANNEL_CRON,
                'expired_cache_entries' => $expiredCache,
                'purged_log_entries' => $purgedLogs,
                'purged_sync_runs' => $purgedRuns,
            ]
        );
    }
}
