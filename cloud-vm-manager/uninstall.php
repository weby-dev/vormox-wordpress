<?php

/**
 * Uninstall routine.
 *
 * Executed by WordPress when the plugin is deleted from the plugins screen.
 * Plugin constants and the plugin bootstrap are not available here, therefore
 * the autoloader is registered manually.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/includes/Autoloader.php';

\CloudVmManager\Autoloader::register('CloudVmManager', __DIR__ . '/includes');

\CloudVmManager\Bootstrap\Uninstaller::uninstall();
