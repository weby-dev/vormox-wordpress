<?php

/**
 * Cron job contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A recurring background job managed by the cron manager.
 */
interface JobInterface
{
    /**
     * WordPress action hook the job listens on. Must start with the "cvm_" prefix.
     */
    public function hook(): string;

    /**
     * Cron schedule slug, for example "hourly" or "cvm_fifteen_minutes".
     */
    public function recurrence(): string;

    /**
     * Execute the job.
     */
    public function run(): void;
}
