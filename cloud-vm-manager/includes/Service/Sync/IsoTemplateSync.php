<?php

/**
 * ISO template synchronisation.
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
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Service\Provider\ProviderGateway;

defined('ABSPATH') || exit;

/**
 * Synchronises the operating system images of one zone.
 *
 * The context carries the zone the images belong to, because the backend scopes
 * the endpoint by zone and the same image identifier may appear in several.
 */
final class IsoTemplateSync extends AbstractResourceSync
{
    /**
     * @var IsoTemplateRepository
     */
    private $templates;

    public function __construct(ProviderGateway $gateway, LoggerInterface $logger, IsoTemplateRepository $templates)
    {
        parent::__construct($gateway, $logger);

        $this->templates = $templates;
    }

    public function resource(): string
    {
        return SyncRun::RESOURCE_ISOS;
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function request(array $context): ApiRequest
    {
        return ApiRequest::get(Endpoints::zoneIsos((int) ($context['zone_remote_id'] ?? 0)));
    }

    protected function repository(): AbstractRepository
    {
        return $this->templates;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function scope(Provider $provider, array $context): array
    {
        return [
            'provider_id' => $provider->id(),
            'zone_remote_id' => (int) ($context['zone_remote_id'] ?? 0),
        ];
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
            'zone_id' => (int) ($context['zone_id'] ?? 0),
            'iso_name' => $this->stringField($item, ['isoName', 'name', 'label']),
            'os_type' => strtoupper($this->stringField($item, ['osType', 'os', 'type'])),
        ];
    }
}
