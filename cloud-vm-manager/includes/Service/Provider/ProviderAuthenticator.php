<?php

/**
 * Provider authentication.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provider;

use CloudVmManager\Contracts\EncryptorInterface;
use CloudVmManager\Contracts\HttpClientInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\AuthenticationException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Support\Jwt;

defined('ABSPATH') || exit;

/**
 * Issues and stores the bearer token used for a provider's API calls.
 *
 * The backend documents no refresh endpoint, so renewing a token means logging
 * in again with the stored credentials. Tokens are kept encrypted and are only
 * decrypted for the duration of a single request.
 */
final class ProviderAuthenticator
{
    /**
     * Seconds before the expiry at which a token is renewed proactively.
     */
    private const EXPIRY_GRACE = 300;

    /**
     * @var HttpClientInterface
     */
    private $client;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var EncryptorInterface
     */
    private $encryptor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Plaintext tokens resolved during this request, keyed by provider.
     *
     * @var array<int, string>
     */
    private $tokens = [];

    public function __construct(
        HttpClientInterface $client,
        ProviderRepository $providers,
        EncryptorInterface $encryptor,
        LoggerInterface $logger
    ) {
        $this->client = $client;
        $this->providers = $providers;
        $this->encryptor = $encryptor;
        $this->logger = $logger;
    }

    /**
     * Usable bearer token, logging in when the stored one is missing or stale.
     *
     * @throws AuthenticationException When no token could be obtained.
     */
    public function token(Provider $provider): string
    {
        if (isset($this->tokens[$provider->id()])) {
            return $this->tokens[$provider->id()];
        }

        $stored = $provider->getEncryptedToken();

        if ($stored !== '' && !$provider->isTokenExpired(self::EXPIRY_GRACE)) {
            $plain = $this->encryptor->decryptSafely($stored);

            if ($plain !== '' && !Jwt::isExpired($plain, self::EXPIRY_GRACE)) {
                $this->tokens[$provider->id()] = $plain;

                return $plain;
            }
        }

        return $this->login($provider);
    }

    /**
     * Force a new login, replacing any stored token.
     *
     * @throws AuthenticationException When the credentials are rejected.
     */
    public function refresh(Provider $provider): string
    {
        unset($this->tokens[$provider->id()]);

        return $this->login($provider);
    }

    /**
     * Authenticate against the backend and store the issued token.
     *
     * @throws AuthenticationException When the credentials are missing or rejected.
     */
    public function login(Provider $provider): string
    {
        $email = $provider->getEmail();
        $password = $this->encryptor->decryptSafely($provider->getEncryptedPassword());

        if ($email === '' || $password === '') {
            $message = __('The provider has no stored credentials to authenticate with.', 'cloud-vm-manager');

            $this->providers->markConnectionResult($provider->id(), false, '', $message);

            throw new AuthenticationException($message, 0, Endpoints::LOGIN);
        }

        $request = ApiRequest::post(
            Endpoints::LOGIN,
            [
                'email' => $email,
                'password' => $password,
            ]
        )->withSslVerify($provider->verifySsl())->withTimeout($provider->getTimeout());

        $response = $this->client->send(
            $request,
            $provider->getApiBaseUrl(),
            ['provider_id' => $provider->id()]
        );

        if (!$response->isSuccessful()) {
            $this->providers->markConnectionResult($provider->id(), false, '', $response->errorMessage());

            throw new AuthenticationException(
                $response->errorMessage(),
                $response->statusCode(),
                Endpoints::LOGIN
            );
        }

        $token = $response->get('token');
        $token = is_string($token) ? $token : '';

        if ($token === '') {
            $message = __('The backend accepted the credentials but returned no token.', 'cloud-vm-manager');

            $this->providers->markConnectionResult($provider->id(), false, '', $message);

            throw new AuthenticationException($message, $response->statusCode(), Endpoints::LOGIN);
        }

        $this->providers->storeToken(
            $provider->id(),
            $this->encryptor->encrypt($token),
            Jwt::expiresAtForStorage($token)
        );

        $this->tokens[$provider->id()] = $token;

        $this->logger->info(
            sprintf('Authenticated with provider "%s".', $provider->getName()),
            [
                'channel' => LogEntry::CHANNEL_API,
                'provider_id' => $provider->id(),
                'endpoint' => Endpoints::LOGIN,
                'status_code' => $response->statusCode(),
            ]
        );

        return $token;
    }

    /**
     * Drop the stored token and mark the provider as disconnected.
     */
    public function disconnect(Provider $provider): void
    {
        unset($this->tokens[$provider->id()]);

        $this->providers->clearToken($provider->id());

        $this->logger->info(
            sprintf('Disconnected from provider "%s".', $provider->getName()),
            [
                'channel' => LogEntry::CHANNEL_ADMIN,
                'provider_id' => $provider->id(),
            ]
        );
    }

    /**
     * Forget the tokens resolved during this request.
     */
    public function flush(): void
    {
        $this->tokens = [];
    }
}
