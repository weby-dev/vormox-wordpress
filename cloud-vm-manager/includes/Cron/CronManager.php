<?php

/**
 * Cron manager.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Cron;

use CloudVmManager\Contracts\JobInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Owns every scheduled job of the plugin.
 *
 * Jobs register themselves here instead of calling `wp_schedule_event()`
 * directly, which keeps scheduling, rescheduling and cleanup in one place.
 */
final class CronManager
{
    /**
     * Custom intervals added to the WordPress schedules.
     */
    public const EVERY_FIVE_MINUTES = 'cvm_five_minutes';
    public const EVERY_FIFTEEN_MINUTES = 'cvm_fifteen_minutes';
    public const EVERY_THIRTY_MINUTES = 'cvm_thirty_minutes';

    /**
     * @var JobInterface[]
     */
    private $jobs = [];

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function addJob(JobInterface $job): void
    {
        $this->jobs[$job->hook()] = $job;
    }

    /**
     * @return JobInterface[]
     */
    public function jobs(): array
    {
        return $this->jobs;
    }

    /**
     * Attach the WordPress hooks of every registered job.
     */
    public function registerHooks(): void
    {
        add_filter('cron_schedules', [$this, 'addSchedules']);

        foreach ($this->jobs as $hook => $job) {
            add_action(
                $hook,
                function () use ($job): void {
                    $this->runJob($job);
                }
            );
        }
    }

    /**
     * Register the custom cron intervals.
     *
     * @param array<string, array{interval: int, display: string}> $schedules
     *
     * @return array<string, array{interval: int, display: string}>
     */
    public function addSchedules($schedules): array
    {
        $schedules = is_array($schedules) ? $schedules : [];

        $schedules[self::EVERY_FIVE_MINUTES] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every five minutes (Cloud VM Manager)', 'cloud-vm-manager'),
        ];

        $schedules[self::EVERY_FIFTEEN_MINUTES] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display' => __('Every fifteen minutes (Cloud VM Manager)', 'cloud-vm-manager'),
        ];

        $schedules[self::EVERY_THIRTY_MINUTES] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Every thirty minutes (Cloud VM Manager)', 'cloud-vm-manager'),
        ];

        return $schedules;
    }

    /**
     * Make sure every job is scheduled with its current recurrence.
     */
    public function scheduleAll(): void
    {
        foreach ($this->jobs as $job) {
            $this->schedule($job);
        }
    }

    /**
     * Schedule a single job, rescheduling it when the recurrence changed.
     */
    public function schedule(JobInterface $job): void
    {
        $hook = $job->hook();
        $recurrence = $job->recurrence();
        $current = wp_get_schedule($hook);

        if ($current === $recurrence) {
            return;
        }

        if ($current !== false) {
            wp_clear_scheduled_hook($hook);
        }

        wp_schedule_event(time() + MINUTE_IN_SECONDS, $recurrence, $hook);
    }

    /**
     * Unschedule every job of the plugin.
     */
    public function clearAll(): void
    {
        foreach (array_keys($this->jobs) as $hook) {
            wp_clear_scheduled_hook($hook);
        }
    }

    /**
     * Timestamp of the next run of a hook, zero when it is not scheduled.
     */
    public function nextRun(string $hook): int
    {
        $timestamp = wp_next_scheduled($hook);

        return $timestamp === false ? 0 : (int) $timestamp;
    }

    /**
     * Execute a job and make sure a failure never breaks the cron run.
     */
    private function runJob(JobInterface $job): void
    {
        $startedAt = microtime(true);

        try {
            $job->run();

            $this->logger->debug(
                sprintf('Cron job "%s" finished.', $job->hook()),
                [
                    'channel' => LogEntry::CHANNEL_CRON,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );
        } catch (Throwable $exception) {
            $this->logger->exception(
                $exception,
                sprintf('Cron job "%s" failed.', $job->hook()),
                ['channel' => LogEntry::CHANNEL_CRON]
            );
        }
    }
}
