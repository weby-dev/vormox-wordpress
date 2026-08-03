<?php

/**
 * Wallet and invoices.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Vm;

use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\ApiResponse;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Arr;

defined('ABSPATH') || exit;

/**
 * Wallet balance, transaction history and invoice documents.
 *
 * The wallet belongs to the account the store operates the backend with, so it
 * is read per provider rather than per customer, and the dashboard presents it
 * as the balance funding that customer's machines.
 */
final class WalletService
{
    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var ProviderRepository
     */
    private $providers;

    public function __construct(ProviderGateway $gateway, ProviderRepository $providers)
    {
        $this->gateway = $gateway;
        $this->providers = $providers;
    }

    /**
     * Current wallet balance of a provider account.
     *
     * @return array{available: bool, balance: float, currency: string, updated_at: string}
     */
    public function balance(int $providerId): array
    {
        $response = $this->request($providerId, ApiRequest::get(Endpoints::WALLET));

        if ($response === null || !$response->isSuccessful()) {
            return [
                'available' => false,
                'balance' => 0.0,
                'currency' => '',
                'updated_at' => '',
            ];
        }

        $data = $response->data();

        return [
            'available' => true,
            'balance' => (float) Arr::get($data, 'balance', 0),
            'currency' => (string) Arr::get($data, 'currency', ''),
            'updated_at' => (string) Arr::first($data, ['updatedAt', 'updated_at'], ''),
        ];
    }

    /**
     * Credits and debits recorded on the wallet.
     *
     * @return array<int, array{amount: float, description: string, timestamp: string}>
     */
    public function transactions(int $providerId, int $limit = 20): array
    {
        $response = $this->request($providerId, ApiRequest::get(Endpoints::WALLET_LOGS));

        if ($response === null || !$response->isSuccessful()) {
            return [];
        }

        $entries = [];

        foreach ($response->list() as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entries[] = [
                'amount' => (float) Arr::get($entry, 'amount', 0),
                'description' => (string) Arr::get($entry, 'description', ''),
                'timestamp' => (string) Arr::first($entry, ['timestamp', 'createdAt'], ''),
            ];
        }

        return array_slice($entries, 0, max(1, $limit));
    }

    /**
     * Machines the backend archived, used for the invoice history.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pastOrders(int $providerId, int $page = 0, int $size = 10): array
    {
        $response = $this->request(
            $providerId,
            ApiRequest::get(
                Endpoints::ORDERS_PAST,
                [
                    'page' => max(0, $page),
                    'size' => max(1, min($size, 50)),
                    'sortBy' => 'deletionTimestamp',
                    'sortDir' => 'desc',
                ]
            )
        );

        if ($response === null || !$response->isSuccessful()) {
            return [];
        }

        $orders = Arr::get($response->data(), 'orders', []);

        return is_array($orders) ? $orders : [];
    }

    /**
     * Download the invoice document of a payment.
     *
     * @return array{available: bool, body: string, content_type: string, message: string}
     */
    public function invoice(int $providerId, int $paymentId): array
    {
        $response = $this->request(
            $providerId,
            ApiRequest::get(Endpoints::invoice($paymentId))->expectingBinary()
        );

        if ($response === null) {
            return [
                'available' => false,
                'body' => '',
                'content_type' => '',
                'message' => __('The invoice could not be downloaded.', 'cloud-vm-manager'),
            ];
        }

        if (!$response->isSuccessful()) {
            return [
                'available' => false,
                'body' => '',
                'content_type' => '',
                'message' => $response->errorMessage(),
            ];
        }

        $contentType = $response->header('content-type');

        return [
            'available' => true,
            'body' => $response->body(),
            'content_type' => $contentType !== '' ? $contentType : 'application/pdf',
            'message' => '',
        ];
    }

    /**
     * Perform a request against a provider account.
     */
    private function request(int $providerId, ApiRequest $request): ?ApiResponse
    {
        $provider = $this->providers->findProvider($providerId);

        if (!$provider instanceof Provider) {
            return null;
        }

        try {
            return $this->gateway->request(
                $provider,
                $request,
                ['channel' => LogEntry::CHANNEL_CUSTOMER, 'provider_id' => $providerId]
            );
        } catch (ApiException $exception) {
            return null;
        }
    }
}
