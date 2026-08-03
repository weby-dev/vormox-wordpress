<?php

/**
 * Base service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Container;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Convenience base class so providers only implement what they need.
 */
abstract class AbstractServiceProvider implements ServiceProviderInterface
{
    public function boot(Container $container): void
    {
    }
}
