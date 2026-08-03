<?php

/**
 * Base exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

use RuntimeException;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Root of the plugin exception hierarchy so callers can catch everything at once.
 */
class CloudVmManagerException extends RuntimeException
{
}
