<?php

/**
 * Bootable contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A service that attaches its own WordPress hooks when the plugin boots.
 */
interface BootableInterface
{
    public function boot(): void;
}
