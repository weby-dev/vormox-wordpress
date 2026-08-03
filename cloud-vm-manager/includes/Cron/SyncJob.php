<?php

/**
 * Catalogue synchronisation job.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Cron;

use CloudVmManager\Contracts\JobInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Service\Sync\CatalogueSynchronizer;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Keeps the provider catalogue current in the background.
 *
 * The recurrence follows the configured synchronisation interval, so changing
 * it in the settings reschedules the job on the next save.
 */
final class SyncJob implements JobInterface
{
    public const HOOK = 'cvm_sync_catalogue';

    private const DEFAULT_RECURRENCE = 'twicedaily';

    /**
     * @var CatalogueSynchronizer
     */
    private $synchronizer;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CatalogueSynchronizer $synchronizer,
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->synchronizer = $synchronizer;
        $this->settings = $settings;
        $this->logger = $logger;
    }

    public function hook(): string
    {
        return self::HOOK;
    }

    public function recurrence(): string
    {
        $interval = $this->settings->getString('sync_interval', self::DEFAULT_RECURRENCE);
        $schedules = wp_get_schedules();

        if (is_array($schedules) && isset($schedules[$interval])) {
            return $interval;
        }

        return self::DEFAULT_RECURRENCE;
    }

    public function run(): void
    {
        if (!$this->settings->getBool('auto_sync_enabled', true)) {
            $this->logger->debug(
                'Automatic synchronisation is disabled, skipping the scheduled run.',
                ['channel' => LogEntry::CHANNEL_CRON]
            );

            return;
        }

        $results = $this->synchronizer->syncAll();

        $this->logger->info(
            sprintf('Scheduled synchronisation covered %d provider(s).', count($results)),
            ['channel' => LogEntry::CHANNEL_CRON]
        );
    }
}
