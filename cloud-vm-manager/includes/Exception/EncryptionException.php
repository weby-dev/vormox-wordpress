<?php

/**
 * Encryption exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Thrown when a value cannot be encrypted, decrypted or authenticated.
 */
class EncryptionException extends CloudVmManagerException
{
}
