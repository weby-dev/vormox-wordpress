<?php

/**
 * API request.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Http;

defined('ABSPATH') || exit;

/**
 * Immutable description of one backend request.
 *
 * Every mutator returns a copy, so a prepared request can be reused safely, for
 * example when a call is retried with a refreshed token.
 */
final class ApiRequest
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';
    public const METHOD_PUT = 'PUT';
    public const METHOD_PATCH = 'PATCH';
    public const METHOD_DELETE = 'DELETE';

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $path;

    /**
     * @var array<string, scalar>
     */
    private $query = [];

    /**
     * @var array<string, mixed>|null
     */
    private $body;

    /**
     * @var array<string, string>
     */
    private $headers = [];

    /**
     * Per request timeout override in seconds. Zero uses the provider default.
     *
     * @var int
     */
    private $timeout = 0;

    /**
     * Per request TLS verification override. Null uses the provider default.
     *
     * @var bool|null
     */
    private $verifySsl;

    /**
     * Whether the response is expected to be binary rather than JSON.
     *
     * @var bool
     */
    private $expectsBinary = false;

    /**
     * @param array<string, scalar>     $query
     * @param array<string, mixed>|null $body
     */
    private function __construct(string $method, string $path, array $query = [], ?array $body = null)
    {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
    }

    /**
     * @param array<string, scalar> $query
     */
    public static function get(string $path, array $query = []): self
    {
        return new self(self::METHOD_GET, $path, $query);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, scalar> $query
     */
    public static function post(string $path, array $body = [], array $query = []): self
    {
        return new self(self::METHOD_POST, $path, $query, $body);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, scalar> $query
     */
    public static function put(string $path, array $body = [], array $query = []): self
    {
        return new self(self::METHOD_PUT, $path, $query, $body);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, scalar> $query
     */
    public static function delete(string $path, array $body = [], array $query = []): self
    {
        return new self(self::METHOD_DELETE, $path, $query, $body === [] ? null : $body);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, scalar>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function body(): ?array
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function verifySsl(): ?bool
    {
        return $this->verifySsl;
    }

    public function expectsBinary(): bool
    {
        return $this->expectsBinary;
    }

    /**
     * Whether an Authorization header is present.
     */
    public function isAuthenticated(): bool
    {
        return isset($this->headers['Authorization']);
    }

    /**
     * @param array<string, scalar> $query
     */
    public function withQuery(array $query): self
    {
        $clone = clone $this;
        $clone->query = array_merge($clone->query, $query);

        return $clone;
    }

    /**
     * @param array<string, mixed> $body
     */
    public function withBody(array $body): self
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $clone = clone $this;
        $clone->headers = array_merge($clone->headers, $headers);

        return $clone;
    }

    public function withBearerToken(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    public function withTimeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = max(0, $seconds);

        return $clone;
    }

    public function withSslVerify(bool $verify): self
    {
        $clone = clone $this;
        $clone->verifySsl = $verify;

        return $clone;
    }

    public function expectingBinary(): self
    {
        $clone = clone $this;
        $clone->expectsBinary = true;

        return $clone;
    }

    /**
     * Absolute URL of the request for the given API base URL.
     */
    public function url(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/') . '/' . ltrim($this->path, '/');

        if ($this->query === []) {
            return $url;
        }

        return add_query_arg(array_map([$this, 'stringifyQueryValue'], $this->query), $url);
    }

    /**
     * @param scalar $value
     */
    private function stringifyQueryValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
