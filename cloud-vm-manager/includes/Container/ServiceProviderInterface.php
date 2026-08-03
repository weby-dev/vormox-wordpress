<?php

/**
 * Service provider contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Container;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A service provider registers services and wires them into WordPress.
 *
 * Registration and booting are split so that every service is known before any
 * of them attaches hooks or resolves its dependencies.
 */
interface ServiceProviderInterface
{
    /**
     * Bind services into the container. No WordPress hook may be registered here.
     */
    public function register(Container $container): void;

    /**
     * Attach WordPress hooks and start the registered services.
     */
    public function boot(Container $container): void;
}
