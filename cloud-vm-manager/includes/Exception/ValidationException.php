<?php

/**
 * Validation exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Thrown when user supplied data does not pass validation.
 */
class ValidationException extends CloudVmManagerException
{
    /**
     * Field name to error message map.
     *
     * @var array<string, string>
     */
    private $errors;

    /**
     * @param array<string, string> $errors
     */
    public function __construct(string $message, array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->errors = $errors;
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
