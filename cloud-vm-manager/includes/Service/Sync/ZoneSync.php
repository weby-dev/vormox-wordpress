<?php

/**
 * Zone synchronisation.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Sync;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Http\ApiRequest;
use CloudVmManager\Http\Endpoints;
use CloudVmManager\Model\Provider;
use CloudVmManager\Model\SyncRun;
use CloudVmManager\Repository\AbstractRepository;
use CloudVmManager\Repository\ZoneRepository;
use CloudVmManager\Service\Provider\ProviderGateway;

defined('ABSPATH') || exit;

/**
 * Synchronises the geographic zones of a provider.
 *
 * The documentation names the endpoint but not the field names of the response,
 * so the identifier is read from `id` and the descriptive fields are resolved
 * from the names a backend commonly uses. The raw item is stored alongside, so
 * nothing is lost when a field is named differently.
 */
final class ZoneSync extends AbstractResourceSync
{
    /**
     * @var ZoneRepository
     */
    private $zones;

    public function __construct(ProviderGateway $gateway, LoggerInterface $logger, ZoneRepository $zones)
    {
        parent::__construct($gateway, $logger);

        $this->zones = $zones;
    }

    public function resource(): string
    {
        return SyncRun::RESOURCE_ZONES;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function request(array $context): ApiRequest
    {
        return ApiRequest::get(Endpoints::ZONES);
    }

    protected function repository(): AbstractRepository
    {
        return $this->zones;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function scope(Provider $provider, array $context): array
    {
        return ['provider_id' => $provider->id()];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function map(array $item, Provider $provider, array $context): array
    {
        return [
            'name' => $this->stringField($item, ['zoneName', 'name', 'title', 'label']),
            'country' => $this->stringField($item, ['country', 'countryName', 'countryCode', 'location']),
            'description' => $this->stringField($item, ['description', 'details']),
        ];
    }
}
