<?php

/**
 * API exception.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Exception;

use Throwable;

defined('ABSPATH') || exit;

/**
 * Thrown when the backend answers with an unexpected status or payload.
 */
class ApiException extends CloudVmManagerException
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var array<string, mixed>
     */
    private $payload;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $message,
        int $statusCode = 0,
        string $endpoint = '',
        array $payload = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->endpoint = $endpoint;
        $this->payload = $payload;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Decoded response payload, when the backend returned JSON.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }
}
