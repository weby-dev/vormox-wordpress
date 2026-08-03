<?php

/**
 * REST API client.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Http;

use CloudVmManager\Contracts\HttpClientInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\TransportException;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Support\Settings;
use CloudVmManager\Support\Str;
use WP_Error;

defined('ABSPATH') || exit;

/**
 * Reusable client for the backend REST API.
 *
 * Handles timeouts, TLS verification, retries with linear backoff, JSON
 * encoding and decoding, and writes every request and response to the API log
 * channel with credentials redacted.
 */
final class ApiClient implements HttpClientInterface
{
    /**
     * Statuses that are worth another attempt.
     *
     * @var int[]
     */
    private const RETRYABLE_STATUSES = [408, 425, 429, 500, 502, 503, 504];

    /**
     * Upper bound for a single attempt, whatever the configuration says.
     */
    private const MAX_TIMEOUT = 120;

    /**
     * Upper bound for the backoff between two attempts.
     */
    private const MAX_RETRY_DELAY = 10;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Settings
     */
    private $settings;

    public function __construct(LoggerInterface $logger, Settings $settings)
    {
        $this->logger = $logger;
        $this->settings = $settings;
    }

    /**
     * @param array<string, mixed> $logContext
     */
    public function send(ApiRequest $request, string $baseUrl, array $logContext = []): ApiResponse
    {
        $url = $request->url($baseUrl);
        $args = $this->buildArgs($request);
        $attempts = $this->maxAttempts();
        $lastError = '';

        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            $startedAt = microtime(true);
            $result = wp_remote_request($url, $args);
            $duration = (int) round((microtime(true) - $startedAt) * 1000);

            if ($result instanceof WP_Error) {
                $lastError = $result->get_error_message();

                $this->logFailure($request, $url, $lastError, $attempt, $attempts, $duration, $logContext);

                if ($attempt < $attempts) {
                    $this->backoff($attempt);

                    continue;
                }

                throw new TransportException(
                    sprintf(
                        /* translators: %s: transport error message. */
                        __('The backend could not be reached: %s', 'cloud-vm-manager'),
                        $lastError
                    ),
                    0,
                    $request->path()
                );
            }

            $response = new ApiResponse(
                (int) wp_remote_retrieve_response_code($result),
                $this->normalizeHeaders($result),
                (string) wp_remote_retrieve_body($result),
                $duration,
                $request->path()
            );

            if ($attempt < $attempts && in_array($response->statusCode(), self::RETRYABLE_STATUSES, true)) {
                $this->logResponse($request, $url, $response, $attempt, $attempts, $logContext);
                $this->backoff($attempt);

                continue;
            }

            $this->logResponse($request, $url, $response, $attempt, $attempts, $logContext);

            return $response;
        }

        throw new TransportException(
            sprintf(
                /* translators: %s: transport error message. */
                __('The backend could not be reached: %s', 'cloud-vm-manager'),
                $lastError
            ),
            0,
            $request->path()
        );
    }

    /**
     * Build the argument array for the WordPress HTTP API.
     *
     * @return array<string, mixed>
     */
    private function buildArgs(ApiRequest $request): array
    {
        $headers = array_merge(
            [
                'Accept' => $request->expectsBinary() ? '*/*' : 'application/json',
            ],
            $request->headers()
        );

        $args = [
            'method' => $request->method(),
            'timeout' => $this->resolveTimeout($request),
            'sslverify' => $request->verifySsl() ?? true,
            'redirection' => 3,
            'headers' => $headers,
            'user-agent' => $this->userAgent(),
        ];

        $body = $request->body();

        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = (string) wp_json_encode($body);
        }

        /**
         * Filter the WordPress HTTP arguments before a backend request is sent.
         *
         * @param array<string, mixed> $args    HTTP arguments.
         * @param ApiRequest           $request Request being sent.
         */
        $filtered = apply_filters('cloud_vm_manager_http_args', $args, $request);

        return is_array($filtered) ? $filtered : $args;
    }

    private function resolveTimeout(ApiRequest $request): int
    {
        $timeout = $request->timeout();

        if ($timeout <= 0) {
            $timeout = $this->settings->getInt('api_timeout', 30);
        }

        return max(5, min($timeout, self::MAX_TIMEOUT));
    }

    private function maxAttempts(): int
    {
        return max(1, min($this->settings->getInt('api_retries', 2), 5) + 1);
    }

    private function backoff(int $attempt): void
    {
        $delay = $this->settings->getInt('api_retry_delay', 2) * $attempt;
        $delay = max(1, min($delay, self::MAX_RETRY_DELAY));

        sleep($delay);
    }

    private function userAgent(): string
    {
        return sprintf('CloudVmManager/%s (WordPress/%s)', CVM_VERSION, get_bloginfo('version'));
    }

    /**
     * @param array<string, mixed>|WP_Error $result
     *
     * @return array<string, string>
     */
    private function normalizeHeaders($result): array
    {
        $headers = wp_remote_retrieve_headers($result);

        if (is_object($headers) && method_exists($headers, 'getAll')) {
            $headers = $headers->getAll();
        }

        if (!is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[(string) $name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $logContext
     */
    private function logResponse(
        ApiRequest $request,
        string $url,
        ApiResponse $response,
        int $attempt,
        int $attempts,
        array $logContext
    ): void {
        $context = array_merge(
            $logContext,
            [
                'channel' => LogEntry::CHANNEL_API,
                'method' => $request->method(),
                'endpoint' => $request->path(),
                'status_code' => $response->statusCode(),
                'duration_ms' => $response->durationMs(),
                'attempt' => $attempt . '/' . $attempts,
                'url' => $this->sanitizeUrl($url),
                'request_body' => $request->body(),
                'response_body' => Str::truncate($response->body(), 2000),
            ]
        );

        $message = sprintf('%s %s responded %d.', $request->method(), $request->path(), $response->statusCode());

        if ($response->isServerError()) {
            $this->logger->error($message, $context);

            return;
        }

        if ($response->isClientError()) {
            $this->logger->warning($message, $context);

            return;
        }

        $this->logger->debug($message, $context);
    }

    /**
     * @param array<string, mixed> $logContext
     */
    private function logFailure(
        ApiRequest $request,
        string $url,
        string $error,
        int $attempt,
        int $attempts,
        int $duration,
        array $logContext
    ): void {
        $this->logger->error(
            sprintf('%s %s failed: %s', $request->method(), $request->path(), $error),
            array_merge(
                $logContext,
                [
                    'channel' => LogEntry::CHANNEL_API,
                    'method' => $request->method(),
                    'endpoint' => $request->path(),
                    'duration_ms' => $duration,
                    'attempt' => $attempt . '/' . $attempts,
                    'url' => $this->sanitizeUrl($url),
                ]
            )
        );
    }

    /**
     * Drop the query string so tokens passed as parameters never reach the log.
     */
    private function sanitizeUrl(string $url): string
    {
        $position = strpos($url, '?');

        return $position === false ? $url : substr($url, 0, $position);
    }
}
