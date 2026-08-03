<?php

/**
 * Provider connection test.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provider;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Exception\TransportException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Support\Arr;

defined('ABSPATH') || exit;

/**
 * Verifies that a provider is reachable and that its credentials work.
 *
 * The test runs two documented calls: the public settings endpoint proves the
 * API base URL resolves and answers, and the login endpoint proves the stored
 * credentials are accepted. Both outcomes are reported separately so an
 * operator can tell a wrong URL from a wrong password.
 */
final class ConnectionTester
{
    /**
     * Payload keys that may carry a platform version.
     *
     * @var string[]
     */
    private const VERSION_KEYS = ['version', 'platformVersion', 'apiVersion', 'appVersion'];

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var ProviderAuthenticator
     */
    private $authenticator;

    /**
     * @var ProviderRepository
     */
    private $providers;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        ProviderGateway $gateway,
        ProviderAuthenticator $authenticator,
        ProviderRepository $providers,
        LoggerInterface $logger
    ) {
        $this->gateway = $gateway;
        $this->authenticator = $authenticator;
        $this->providers = $providers;
        $this->logger = $logger;
    }

    /**
     * Run the reachability and the credential check.
     */
    public function test(Provider $provider): ConnectionResult
    {
        $startedAt = microtime(true);
        $steps = [];
        $platformVersion = '';
        $companyName = '';

        try {
            $response = $this->gateway->publicRequest($provider, ApiRequest::get(Endpoints::PUBLIC_SETTINGS));

            if ($response->isSuccessful()) {
                $steps['reachability'] = __('The API answered.', 'cloud-vm-manager');

                $version = Arr::first($response->data(), self::VERSION_KEYS, '');
                $platformVersion = is_scalar($version) ? (string) $version : '';

                $company = Arr::get($response->data(), 'companyName', '');
                $companyName = is_scalar($company) ? (string) $company : '';
            } else {
                $steps['reachability'] = $response->errorMessage();
            }
        } catch (TransportException $exception) {
            return $this->failure($provider, $exception->getMessage(), $steps, $startedAt);
        }

        try {
            $this->authenticator->refresh($provider);
            $steps['authentication'] = __('The credentials were accepted.', 'cloud-vm-manager');
        } catch (ApiException $exception) {
            $steps['authentication'] = $exception->getMessage();

            return $this->failure(
                $provider,
                $exception->getMessage(),
                $steps,
                $startedAt,
                $exception->statusCode()
            );
        }

        $this->providers->markConnectionResult($provider->id(), true, $platformVersion);

        $this->logger->info(
            sprintf('Connection test for provider "%s" succeeded.', $provider->getName()),
            [
                'channel' => LogEntry::CHANNEL_ADMIN,
                'provider_id' => $provider->id(),
            ]
        );

        return new ConnectionResult(
            true,
            __('Connected successfully.', 'cloud-vm-manager'),
            $steps,
            $platformVersion,
            $companyName,
            200,
            $this->elapsed($startedAt)
        );
    }

    /**
     * @param array<string, string> $steps
     */
    private function failure(
        Provider $provider,
        string $message,
        array $steps,
        float $startedAt,
        int $statusCode = 0
    ): ConnectionResult {
        $this->providers->markConnectionResult($provider->id(), false, '', $message);

        $this->logger->warning(
            sprintf('Connection test for provider "%s" failed: %s', $provider->getName(), $message),
            [
                'channel' => LogEntry::CHANNEL_ADMIN,
                'provider_id' => $provider->id(),
                'status_code' => $statusCode,
            ]
        );

        return new ConnectionResult(false, $message, $steps, '', '', $statusCode, $this->elapsed($startedAt));
    }

    private function elapsed(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
