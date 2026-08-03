<?php

/**
 * Missing service exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Thrown when the container has no entry for an identifier.
 */
class ServiceNotFoundException extends ContainerException
{
}
