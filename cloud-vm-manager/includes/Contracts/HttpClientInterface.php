<?php

/**
 * HTTP client contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

use CloudVmManager\Exception\TransportException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\ApiResponse;

defined('ABSPATH') || exit;

/**
 * Transport used to talk to the backend REST API.
 */
interface HttpClientInterface
{
    /**
     * Send a request and return the response, whatever its status code is.
     *
     * @param string               $baseUrl    API base URL of the provider.
     * @param array<string, mixed> $logContext Extra context stored with the log entry.
     *
     * @throws TransportException When the request never reached the backend.
     */
    public function send(ApiRequest $request, string $baseUrl, array $logContext = []): ApiResponse;
}
