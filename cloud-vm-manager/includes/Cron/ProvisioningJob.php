<?php

/**
 * Provisioning retry job.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Cron;

use CloudVmManager\Contracts\JobInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Service\Provisioning\ProvisioningService;

defined('ABSPATH') || exit;

/**
 * Picks up machines whose provisioning is due for another attempt.
 *
 * A creation request that the backend accepted but has not finished is the
 * normal case, so this job runs often enough to finish those quickly without
 * hammering the API.
 */
final class ProvisioningJob implements JobInterface
{
    public const HOOK = 'cvm_provisioning_retry';

    /**
     * Machines handled per run, so one job never runs long.
     */
    private const BATCH_SIZE = 10;

    /**
     * @var ProvisioningService
     */
    private $provisioning;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(ProvisioningService $provisioning, LoggerInterface $logger)
    {
        $this->provisioning = $provisioning;
        $this->logger = $logger;
    }

    public function hook(): string
    {
        return self::HOOK;
    }

    public function recurrence(): string
    {
        return CronManager::EVERY_FIVE_MINUTES;
    }

    public function run(): void
    {
        $results = $this->provisioning->processRetryQueue(self::BATCH_SIZE);

        if ($results === []) {
            return;
        }

        $completed = 0;

        foreach ($results as $result) {
            if ($result->isCompleted()) {
                ++$completed;
            }
        }

        $this->logger->info(
            sprintf('Retried %d machine(s), %d finished.', count($results), $completed),
            ['channel' => LogEntry::CHANNEL_CRON]
        );
    }
}
