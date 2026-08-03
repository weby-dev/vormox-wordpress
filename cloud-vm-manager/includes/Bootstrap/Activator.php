<?php

/**
 * Activation routine.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Bootstrap;

use CloudVmManager\Cron\CronManager;
use CloudVmManager\Database\Installer;
use CloudVmManager\Plugin;
use CloudVmManager\Support\Settings;
use WP_Site;

defined('ABSPATH') || exit;

/**
 * Prepares the site when the plugin is activated.
 */
final class Activator
{
    /**
     * Maximum number of sites migrated during a network wide activation.
     *
     * Larger networks are upgraded lazily by the migrator on the first request
     * of each site, which keeps the activation request within its time limit.
     */
    private const NETWORK_SITE_LIMIT = 100;

    /**
     * @param bool $networkWide Whether the plugin was activated network wide.
     */
    public static function activate(bool $networkWide = false): void
    {
        $requirements = new Requirements();

        if (!$requirements->isSatisfied()) {
            deactivate_plugins(CVM_PLUGIN_BASENAME);

            wp_die(
                esc_html(implode(' ', $requirements->errors())),
                esc_html__('Cloud VM Manager cannot be activated', 'cloud-vm-manager'),
                ['back_link' => true]
            );
        }

        if ($networkWide && is_multisite()) {
            $sites = get_sites(
                [
                    'fields' => 'ids',
                    'number' => self::NETWORK_SITE_LIMIT,
                ]
            );

            foreach ($sites as $siteId) {
                switch_to_blog((int) $siteId);
                self::installSite();
                restore_current_blog();
            }

            return;
        }

        self::installSite();
    }

    /**
     * Install the plugin on a site created after a network wide activation.
     */
    public static function initializeNewSite(WP_Site $site): void
    {
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active_for_network(CVM_PLUGIN_BASENAME)) {
            return;
        }

        switch_to_blog((int) $site->blog_id);
        self::installSite();
        restore_current_blog();
    }

    /**
     * Create the tables, seed the settings and schedule the cron jobs of the current site.
     */
    private static function installSite(): void
    {
        $plugin = Plugin::instance();
        $plugin->register();

        $container = $plugin->container();

        /** @var Installer $installer */
        $installer = $container->get(Installer::class);
        $installer->install();

        /** @var Settings $settings */
        $settings = $container->get(Settings::class);
        $settings->seedDefaults();

        /** @var CronManager $cron */
        $cron = $container->get(CronManager::class);
        $cron->scheduleAll();

        if (get_option('cvm_installed_at') === false) {
            add_option('cvm_installed_at', gmdate('Y-m-d H:i:s'), '', 'no');
        }

        update_option('cvm_version', CVM_VERSION, 'no');

        /**
         * Fires after the plugin finished installing on a site.
         */
        do_action('cloud_vm_manager_activated');
    }
}
