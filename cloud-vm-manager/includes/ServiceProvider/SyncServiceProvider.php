<?php

/**
 * Synchronisation service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Repository\BandwidthPlanRepository;
use CloudVmManager\Repository\CpuPlanRepository;
use CloudVmManager\Repository\DiskPlanRepository;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\RamPlanRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Repository\ZoneRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Service\Sync\CatalogueSynchronizer;
use CloudVmManager\Service\Sync\IsoTemplateSync;
use CloudVmManager\Service\Sync\PlanSync;
use CloudVmManager\Service\Sync\ZoneSync;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Registers the catalogue synchronisers and the orchestrator.
 */
final class SyncServiceProvider extends AbstractServiceProvider
{
    /**
     * Resource type to plan repository.
     *
     * @var array<string, string>
     */
    private const PLAN_REPOSITORIES = [
        PricingRule::RESOURCE_CPU => CpuPlanRepository::class,
        PricingRule::RESOURCE_RAM => RamPlanRepository::class,
        PricingRule::RESOURCE_DISK => DiskPlanRepository::class,
        PricingRule::RESOURCE_BANDWIDTH => BandwidthPlanRepository::class,
    ];

    public function register(Container $container): void
    {
        $container->singleton(
            ZoneSync::class,
            static function (Container $c): ZoneSync {
                return new ZoneSync(
                    $c->get(ProviderGateway::class),
                    $c->get(LoggerInterface::class),
                    $c->get(ZoneRepository::class)
                );
            }
        );

        $container->singleton(
            IsoTemplateSync::class,
            static function (Container $c): IsoTemplateSync {
                return new IsoTemplateSync(
                    $c->get(ProviderGateway::class),
                    $c->get(LoggerInterface::class),
                    $c->get(IsoTemplateRepository::class)
                );
            }
        );

        foreach (self::PLAN_REPOSITORIES as $resourceType => $repositoryClass) {
            $container->singleton(
                self::planSyncId($resourceType),
                static function (Container $c) use ($resourceType, $repositoryClass): PlanSync {
                    return new PlanSync(
                        $c->get(ProviderGateway::class),
                        $c->get(LoggerInterface::class),
                        $resourceType,
                        $c->get($repositoryClass),
                        $c->get(PricingRepository::class),
                        $c->get(Settings::class)
                    );
                }
            );
        }

        $container->singleton(
            CatalogueSynchronizer::class,
            static function (Container $c): CatalogueSynchronizer {
                $plans = [];

                foreach (array_keys(self::PLAN_REPOSITORIES) as $resourceType) {
                    $plans[] = $c->get(self::planSyncId($resourceType));
                }

                return new CatalogueSynchronizer(
                    $c->get(ZoneSync::class),
                    $c->get(IsoTemplateSync::class),
                    $plans,
                    $c->get(ZoneRepository::class),
                    $c->get(ProviderRepository::class),
                    $c->get(SyncRunRepository::class),
                    $c->get(Cache::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );
    }

    /**
     * Container identifier of the synchroniser of one resource.
     */
    public static function planSyncId(string $resourceType): string
    {
        return PlanSync::class . '.' . $resourceType;
    }
}
