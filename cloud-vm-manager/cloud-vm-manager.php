<?php

/**
 * Plugin Name:          Cloud VM Manager
 * Plugin URI:           https://github.com/weby-dev/vormox-wordpress
 * Description:          Sell, provision and manage cloud virtual machines directly from WordPress using WooCommerce.
 * Version:              1.0.0
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Author:               Weby
 * Author URI:           https://github.com/weby-dev
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          cloud-vm-manager
 * Domain Path:          /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.9
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager;

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use CloudVmManager\Bootstrap\Activator;
use CloudVmManager\Bootstrap\Deactivator;
use CloudVmManager\Bootstrap\Requirements;
use WP_Site;

defined('ABSPATH') || exit;

define('CVM_VERSION', '1.0.0');
define('CVM_DB_VERSION', '1.0.3');
define('CVM_PLUGIN_FILE', __FILE__);
define('CVM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CVM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CVM_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CVM_MINIMUM_PHP', '7.4');
define('CVM_MINIMUM_WP', '6.0');
define('CVM_MINIMUM_WC', '7.0');

require_once CVM_PLUGIN_DIR . 'includes/Autoloader.php';

Autoloader::register(__NAMESPACE__, CVM_PLUGIN_DIR . 'includes');

/*
 * Composer is only required for development tooling. When a vendor directory is
 * shipped (or built on the server) its autoloader is honoured as well.
 */
if (is_readable(CVM_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once CVM_PLUGIN_DIR . 'vendor/autoload.php';
}

register_activation_hook(
    __FILE__,
    /**
     * @param bool $network_wide Whether the plugin is activated for the whole network.
     */
    static function ($network_wide = false): void {
        Activator::activate((bool) $network_wide);
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        Deactivator::deactivate();
    }
);

/*
 * Install the plugin tables whenever a new site is created inside a multisite
 * network while the plugin is network activated.
 */
add_action(
    'wp_initialize_site',
    /**
     * @param WP_Site $site Newly created site.
     */
    static function ($site): void {
        if ($site instanceof WP_Site) {
            Activator::initializeNewSite($site);
        }
    },
    20
);

/*
 * Declare compatibility with WooCommerce High Performance Order Storage. This
 * must be registered before WooCommerce initialises its feature flags.
 */
add_action(
    'before_woocommerce_init',
    static function (): void {
        if (class_exists(FeaturesUtil::class)) {
            FeaturesUtil::declare_compatibility('custom_order_tables', CVM_PLUGIN_FILE, true);
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        $requirements = new Requirements();

        if (!$requirements->isSatisfied()) {
            $requirements->registerAdminNotices();

            return;
        }

        Plugin::instance()->boot();
    },
    5
);
