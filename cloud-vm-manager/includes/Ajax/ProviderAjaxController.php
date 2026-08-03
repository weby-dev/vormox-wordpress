<?php

/**
 * Provider AJAX endpoints.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

use CloudVmManager\Admin\Access;
use CloudVmManager\Exception\ApiException;
use CloudVmManager\Model\Provider;
use CloudVmManager\Service\Provider\ConnectionTester;
use CloudVmManager\Service\Provider\ProviderAuthenticator;
use CloudVmManager\Service\Provider\ProviderService;

defined('ABSPATH') || exit;

/**
 * Connection actions of the providers screen.
 *
 * All four actions answer with the same status payload so the script can update
 * the row without reloading the page.
 */
final class ProviderAjaxController extends AbstractAjaxController
{
    public const ACTION_TEST = 'cvm_test_connection';
    public const ACTION_CONNECT = 'cvm_connect_provider';
    public const ACTION_DISCONNECT = 'cvm_disconnect_provider';
    public const ACTION_REFRESH = 'cvm_refresh_token';

    /**
     * @var ProviderService
     */
    private $providers;

    /**
     * @var ConnectionTester
     */
    private $tester;

    /**
     * @var ProviderAuthenticator
     */
    private $authenticator;

    public function __construct(
        ProviderService $providers,
        ConnectionTester $tester,
        ProviderAuthenticator $authenticator
    ) {
        $this->providers = $providers;
        $this->tester = $tester;
        $this->authenticator = $authenticator;
    }

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION_TEST, [$this, 'testConnection']);
        add_action('wp_ajax_' . self::ACTION_CONNECT, [$this, 'connect']);
        add_action('wp_ajax_' . self::ACTION_DISCONNECT, [$this, 'disconnect']);
        add_action('wp_ajax_' . self::ACTION_REFRESH, [$this, 'refreshToken']);
    }

    /**
     * Check reachability and credentials without changing the configuration.
     */
    public function testConnection(): void
    {
        $provider = $this->requireProvider();
        $result = $this->tester->test($provider);
        $payload = ['result' => $result->toArray()] + $this->state($provider->id());

        if (!$result->isSuccessful()) {
            $this->failure($result->message(), 200, $payload);

            return;
        }

        $this->success($payload);
    }

    /**
     * Authenticate and store a token.
     */
    public function connect(): void
    {
        $provider = $this->requireProvider();

        try {
            $this->authenticator->login($provider);
        } catch (ApiException $exception) {
            $this->failure($exception->getMessage(), 200, $this->state($provider->id()));

            return;
        }

        $this->success(
            ['message' => __('Connected successfully.', 'cloud-vm-manager')] + $this->state($provider->id())
        );
    }

    /**
     * Forget the stored token.
     */
    public function disconnect(): void
    {
        $provider = $this->requireProvider();

        $this->authenticator->disconnect($provider);

        $this->success(
            ['message' => __('The provider was disconnected.', 'cloud-vm-manager')] + $this->state($provider->id())
        );
    }

    /**
     * Replace the stored token with a freshly issued one.
     */
    public function refreshToken(): void
    {
        $provider = $this->requireProvider();

        try {
            $this->authenticator->refresh($provider);
        } catch (ApiException $exception) {
            $this->failure($exception->getMessage(), 200, $this->state($provider->id()));

            return;
        }

        $this->success(
            ['message' => __('A new token was issued.', 'cloud-vm-manager')] + $this->state($provider->id())
        );
    }

    /**
     * Authorise the request and load the addressed provider.
     */
    private function requireProvider(): Provider
    {
        $this->authorize(Access::AJAX_NONCE, Access::capability());

        $provider = $this->providers->find($this->intParam('provider_id'));

        if ($provider instanceof Provider) {
            return $provider;
        }

        $this->failure(__('That provider no longer exists.', 'cloud-vm-manager'), 404);

        exit;
    }

    /**
     * Current connection state of a provider, reloaded after the action.
     *
     * @return array<string, mixed>
     */
    private function state(int $providerId): array
    {
        $provider = $this->providers->find($providerId);

        if ($provider === null) {
            return [];
        }

        return [
            'status' => [
                'value' => $provider->getStatus(),
                'label' => $this->statusLabel($provider->getStatus()),
                'connected' => $provider->isConnected(),
                'platform_version' => $provider->getPlatformVersion(),
                'last_error' => $provider->getLastError(),
                'last_connected_at' => $provider->getString('last_connected_at'),
            ],
        ];
    }

    private function statusLabel(string $status): string
    {
        switch ($status) {
            case Provider::STATUS_CONNECTED:
                return __('Connected', 'cloud-vm-manager');
            case Provider::STATUS_ERROR:
                return __('Error', 'cloud-vm-manager');
            default:
                return __('Disconnected', 'cloud-vm-manager');
        }
    }
}
