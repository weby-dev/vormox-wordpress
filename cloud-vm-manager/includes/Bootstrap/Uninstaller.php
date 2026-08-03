<?php

/**
 * Uninstall routine.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Bootstrap;

use CloudVmManager\Database\Installer;
use CloudVmManager\Database\Schema;
use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Support\Settings;
use wpdb;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Removes the plugin footprint when the user deletes the plugin.
 *
 * Tables and settings are only dropped when the "delete data on uninstall"
 * setting is enabled, which protects stores that merely reinstall the plugin.
 */
final class Uninstaller
{
    /**
     * Options owned by the plugin.
     *
     * @var string[]
     */
    private const OPTIONS = [
        Settings::OPTION_KEY,
        'cvm_db_version',
        'cvm_version',
        'cvm_installed_at',
        'cvm_encryption_material',
    ];

    /**
     * Maximum number of sites cleaned during a network uninstall.
     */
    private const NETWORK_SITE_LIMIT = 100;

    public static function uninstall(): void
    {
        if (is_multisite()) {
            $sites = get_sites(
                [
                    'fields' => 'ids',
                    'number' => self::NETWORK_SITE_LIMIT,
                ]
            );

            foreach ($sites as $siteId) {
                switch_to_blog((int) $siteId);
                self::uninstallSite();
                restore_current_blog();
            }

            return;
        }

        self::uninstallSite();
    }

    /**
     * Remove the plugin data of the current site.
     */
    private static function uninstallSite(): void
    {
        global $wpdb;

        if (!$wpdb instanceof wpdb) {
            return;
        }

        $settings = new Settings();

        if (!$settings->getBool('delete_data_on_uninstall')) {
            return;
        }

        $registry = new TableRegistry($wpdb);
        $installer = new Installer($wpdb, new Schema($registry), $registry);
        $installer->dropTables();

        foreach (self::OPTIONS as $option) {
            delete_option($option);
        }

        self::clearScheduledEvents();
    }

    /**
     * Unschedule every cron event registered by the plugin.
     */
    private static function clearScheduledEvents(): void
    {
        $crons = _get_cron_array();

        if (!is_array($crons)) {
            return;
        }

        foreach ($crons as $timestamp => $hooks) {
            foreach (array_keys($hooks) as $hook) {
                if (strpos((string) $hook, 'cvm_') === 0) {
                    wp_clear_scheduled_hook((string) $hook);
                }
            }
        }
    }
}
