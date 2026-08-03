<?php

/**
 * API response.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Http;

use CloudVmManager\Exception\ApiException;
use CloudVmManager\Exception\AuthenticationException;
use CloudVmManager\Support\Arr;

defined('ABSPATH') || exit;

/**
 * Immutable result of one backend request.
 *
 * The body is decoded once. `data()` always returns an array so callers never
 * have to guard against a malformed payload, and `assertSuccessful()` turns a
 * failure status into a typed exception carrying the backend error message.
 */
final class ApiResponse
{
    /**
     * Keys the backend uses to describe an error.
     *
     * @var string[]
     */
    private const ERROR_KEYS = ['error', 'message', 'detail', 'errorMessage'];

    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var array<string, string>
     */
    private $headers;

    /**
     * @var string
     */
    private $body;

    /**
     * @var array<string, mixed>|array<int, mixed>|null
     */
    private $decoded;

    /**
     * @var int
     */
    private $durationMs;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        int $statusCode,
        array $headers,
        string $body,
        int $durationMs = 0,
        string $endpoint = ''
    ) {
        $this->statusCode = $statusCode;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
        $this->durationMs = $durationMs;
        $this->endpoint = $endpoint;
        $this->decoded = $this->decode($body);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function body(): string
    {
        return $this->body;
    }

    public function durationMs(): int
    {
        return $this->durationMs;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    public function isJson(): bool
    {
        return $this->decoded !== null;
    }

    /**
     * Decoded payload. Returns an empty array when the body was not JSON.
     *
     * @return array<string, mixed>|array<int, mixed>
     */
    public function data(): array
    {
        return $this->decoded ?? [];
    }

    /**
     * Decoded payload as a list, for endpoints that return a JSON array.
     *
     * @return array<int, mixed>
     */
    public function list(): array
    {
        $data = $this->data();

        return Arr::isList($data) ? $data : [];
    }

    /**
     * Read a value from the decoded payload using dot notation.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return Arr::get($this->data(), $key, $default);
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401 || $this->statusCode === 403;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    /**
     * Error message reported by the backend, or a generic status description.
     */
    public function errorMessage(): string
    {
        $data = $this->data();

        foreach (self::ERROR_KEYS as $key) {
            $value = Arr::get($data, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if ($this->statusCode === 0) {
            return __('The backend did not return a response.', 'cloud-vm-manager');
        }

        return sprintf(
            /* translators: %d: HTTP status code. */
            __('The backend answered with HTTP status %d.', 'cloud-vm-manager'),
            $this->statusCode
        );
    }

    /**
     * Return the response, or throw when the status is not successful.
     *
     * @throws AuthenticationException When the backend rejected the credentials.
     * @throws ApiException            For every other unsuccessful status.
     */
    public function assertSuccessful(): self
    {
        if ($this->isSuccessful()) {
            return $this;
        }

        $payload = Arr::isList($this->data()) ? [] : $this->data();

        if ($this->isUnauthorized()) {
            throw new AuthenticationException($this->errorMessage(), $this->statusCode, $this->endpoint, $payload);
        }

        throw new ApiException($this->errorMessage(), $this->statusCode, $this->endpoint, $payload);
    }

    /**
     * @return array<string, mixed>|array<int, mixed>|null
     */
    private function decode(string $body)
    {
        if (trim($body) === '') {
            return null;
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (is_array($decoded)) {
            return $decoded;
        }

        return ['value' => $decoded];
    }
}
