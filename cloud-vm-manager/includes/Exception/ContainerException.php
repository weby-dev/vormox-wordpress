<?php

/**
 * Container exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Thrown when a service exists but cannot be built.
 */
class ContainerException extends CloudVmManagerException
{
}
