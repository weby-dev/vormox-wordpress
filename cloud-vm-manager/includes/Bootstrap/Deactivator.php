<?php

/**
 * Deactivation routine.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Bootstrap;

use CloudVmManager\Cron\CronManager;
use CloudVmManager\Plugin;

defined('ABSPATH') || exit;

/**
 * Cleans up transient runtime state when the plugin is deactivated.
 *
 * Persistent data (tables, settings, orders and logs) is preserved so that the
 * plugin can be reactivated without any loss. Data removal only happens during
 * uninstall and only when explicitly enabled in the settings.
 */
final class Deactivator
{
    public static function deactivate(): void
    {
        $plugin = Plugin::instance();
        $plugin->register();

        /** @var CronManager $cron */
        $cron = $plugin->container()->get(CronManager::class);
        $cron->clearAll();

        /**
         * Fires after the plugin has been deactivated.
         */
        do_action('cloud_vm_manager_deactivated');
    }
}
