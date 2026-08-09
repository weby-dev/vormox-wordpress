<?php

/**
 * Usage collection job.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Cron;

use CloudVmManager\Contracts\JobInterface;
use CloudVmManager\Service\Vm\UsageService;

defined('ABSPATH') || exit;

/**
 * Refreshes the disk and transfer figures of the machines that are most out of
 * date, a batch at a time so one run never grows with the number of machines.
 */
final class UsageJob implements JobInterface
{
    public const HOOK = 'cvm_usage_refresh';

    /**
     * Machines refreshed per run.
     */
    private const BATCH_SIZE = 20;

    /**
     * @var UsageService
     */
    private $usage;

    public function __construct(UsageService $usage)
    {
        $this->usage = $usage;
    }

    public function hook(): string
    {
        return self::HOOK;
    }

    public function recurrence(): string
    {
        return 'hourly';
    }

    public function run(): void
    {
        $this->usage->refreshBatch(self::BATCH_SIZE);
    }
}
