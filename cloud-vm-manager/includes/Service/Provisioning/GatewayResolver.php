<?php

/**
 * Payment gateway resolution.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Provisioning;

use CloudVmManager\Exception\ApiException;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\Provider;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Decides which backend payment gateway a creation request names.
 *
 * The create endpoint rejects a request without a gateway, so one is always
 * supplied. The configured gateway wins when the backend offers it, otherwise
 * the first gateway the backend reports is used. The list is cached, because it
 * changes far less often than machines are created.
 */
final class GatewayResolver
{
    private const CACHE_KEY = 'payment-gateways';
    private const CACHE_TTL = 3600;
    private const FALLBACK = 'CASHFREE';

    /**
     * @var ProviderGateway
     */
    private $gateway;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Cache
     */
    private $cache;

    public function __construct(ProviderGateway $gateway, Settings $settings, Cache $cache)
    {
        $this->gateway = $gateway;
        $this->settings = $settings;
        $this->cache = $cache;
    }

    /**
     * Gateway name to send with a creation or top up request.
     */
    public function resolve(Provider $provider): string
    {
        $configured = strtoupper(trim($this->settings->getString('provisioning_gateway', self::FALLBACK)));
        $available = $this->available($provider);

        if ($available === []) {
            return $configured !== '' ? $configured : self::FALLBACK;
        }

        if ($configured !== '' && in_array($configured, $available, true)) {
            return $configured;
        }

        return $available[0];
    }

    /**
     * Gateways the backend reports, upper cased.
     *
     * @return string[]
     */
    public function available(Provider $provider): array
    {
        $cached = $this->cache->get($this->cacheKey($provider));

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = $this->gateway->request(
                $provider,
                ApiRequest::get(Endpoints::PAYMENT_GATEWAYS),
                ['channel' => LogEntry::CHANNEL_PROVISIONING]
            );

            $response->assertSuccessful();
        } catch (ApiException $exception) {
            return [];
        }

        $gateways = [];

        foreach ($response->list() as $entry) {
            if (is_string($entry) && $entry !== '') {
                $gateways[] = strtoupper($entry);
            }
        }

        if ($gateways !== []) {
            $this->cache->put($this->cacheKey($provider), $gateways, self::CACHE_TTL, $provider->id());
        }

        return $gateways;
    }

    private function cacheKey(Provider $provider): string
    {
        return self::CACHE_KEY . ':' . $provider->id();
    }
}
