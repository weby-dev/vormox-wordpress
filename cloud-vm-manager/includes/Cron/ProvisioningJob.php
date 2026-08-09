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

    /**
     * Listen for the one-off check queued when a machine is created.
     *
     * A machine builds in about a minute, so waiting for the next five minute
     * tick would make the customer stare at a spinner for no reason.
     */
    public function registerBuildCheck(): void
    {
        add_action(ProvisioningService::BUILD_CHECK_HOOK, [$this, 'checkOne'], 10, 1);
    }

    /**
     * Resolve one machine that was created a moment ago.
     *
     * @param mixed $orderId Local order row identifier passed by the event.
     */
    public function checkOne($orderId = 0): void
    {
        $result = $this->provisioning->refresh((int) $orderId);

        if ($result === null || !$result->isCompleted()) {
            return;
        }

        $this->logger->info(
            sprintf('Machine of row %d finished starting up.', (int) $orderId),
            ['channel' => LogEntry::CHANNEL_CRON]
        );
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
