<?php

/**
 * Transport exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when the HTTP request never reached the backend.
 *
 * Covers DNS failures, TLS errors, connection timeouts and every other
 * `WP_Error` returned by the WordPress HTTP API after the retries ran out.
 */
class TransportException extends ApiException
{
}
