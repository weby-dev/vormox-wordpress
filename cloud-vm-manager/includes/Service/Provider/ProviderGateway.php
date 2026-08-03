<?php

/**
 * Provider API gateway.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provider;

use CloudVmManager\Contracts\HttpClientInterface;
use CloudVmManager\Exception\AuthenticationException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\ApiResponse;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\ProviderRepository;

defined('ABSPATH') || exit;

/**
 * Binds a provider record to the HTTP client.
 *
 * Applies the provider's base URL, timeout and TLS setting, attaches the bearer
 * token, and transparently re-authenticates once when the backend rejects a
 * token that expired earlier than its claim announced. Every other layer talks
 * to the backend through this class.
 */
final class ProviderGateway
{
    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var ProviderAuthenticator
     */
    private $authenticator;

    /**
     * @var ProviderRepository
     */
    private $providers;

    public function __construct(
        HttpClientInterface $client,
        ProviderAuthenticator $authenticator,
        ProviderRepository $providers
    ) {
        $this->client = $client;
        $this->authenticator = $authenticator;
        $this->providers = $providers;
    }

    /**
     * Send an authenticated request on behalf of the provider account.
     *
     * @param array<string, mixed> $logContext
     *
     * @throws AuthenticationException When no token could be obtained.
     */
    public function request(Provider $provider, ApiRequest $request, array $logContext = []): ApiResponse
    {
        $token = $this->authenticator->token($provider);
        $context = array_merge(['provider_id' => $provider->id()], $logContext);

        $response = $this->client->send(
            $this->prepare($provider, $request)->withBearerToken($token),
            $provider->getApiBaseUrl(),
            $context
        );

        if (!$response->isUnauthorized()) {
            return $response;
        }

        $refreshed = $this->providers->findProvider($provider->id());
        $token = $this->authenticator->refresh($refreshed ?? $provider);

        return $this->client->send(
            $this->prepare($provider, $request)->withBearerToken($token),
            $provider->getApiBaseUrl(),
            $context
        );
    }

    /**
     * Send a request to a public endpoint, without a token.
     *
     * @param array<string, mixed> $logContext
     */
    public function publicRequest(Provider $provider, ApiRequest $request, array $logContext = []): ApiResponse
    {
        return $this->client->send(
            $this->prepare($provider, $request),
            $provider->getApiBaseUrl(),
            array_merge(['provider_id' => $provider->id()], $logContext)
        );
    }

    /**
     * Apply the connection settings of the provider to a request.
     */
    private function prepare(Provider $provider, ApiRequest $request): ApiRequest
    {
        if ($request->timeout() <= 0) {
            $request = $request->withTimeout($provider->getTimeout());
        }

        if ($request->verifySsl() === null) {
            $request = $request->withSslVerify($provider->verifySsl());
        }

        return $request;
    }
}
