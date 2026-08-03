<?php

/**
 * Authentication exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || exit;

/**
 * Thrown when credentials are rejected or no token could be issued.
 */
class AuthenticationException extends ApiException
{
}
