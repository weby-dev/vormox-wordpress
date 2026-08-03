<?php

/**
 * Connection test result.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provider;

use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * Outcome of a provider connection test.
 */
final class ConnectionResult implements JsonSerializable
{
    /**
     * @var bool
     */
    private $successful;

    /**
     * @var string
     */
    private $message;

    /**
     * @var string
     */
    private $platformVersion;

    /**
     * @var string
     */
    private $companyName;

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var int
     */
    private $durationMs;

    /**
     * Step name to human readable outcome.
     *
     * @var array<string, string>
     */
    private $steps;

    /**
     * @param array<string, string> $steps
     */
    public function __construct(
        bool $successful,
        string $message,
        array $steps = [],
        string $platformVersion = '',
        string $companyName = '',
        int $statusCode = 0,
        int $durationMs = 0
    ) {
        $this->successful = $successful;
        $this->message = $message;
        $this->steps = $steps;
        $this->platformVersion = $platformVersion;
        $this->companyName = $companyName;
        $this->statusCode = $statusCode;
        $this->durationMs = $durationMs;
    }

    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function platformVersion(): string
    {
        return $this->platformVersion;
    }

    public function companyName(): string
    {
        return $this->companyName;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    /**
     * @return array<string, string>
     */
    public function steps(): array
    {
        return $this->steps;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'message' => $this->message,
            'platform_version' => $this->platformVersion,
            'company_name' => $this->companyName,
            'status_code' => $this->statusCode,
            'duration_ms' => $this->durationMs,
            'steps' => $this->steps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
