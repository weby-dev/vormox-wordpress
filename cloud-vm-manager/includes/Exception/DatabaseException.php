<?php

/**
 * Database exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Thrown when a database statement fails or is rejected by the repository guards.
 */
class DatabaseException extends CloudVmManagerException
{
}
