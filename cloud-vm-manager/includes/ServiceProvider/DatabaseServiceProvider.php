<?php

/**
 * Database service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Database\Installer;
use CloudVmManager\Database\Migrator;
use CloudVmManager\Database\Schema;
use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Repository\AbstractRepository;
use CloudVmManager\Repository\BandwidthPlanRepository;
use CloudVmManager\Repository\CacheRepository;
use CloudVmManager\Repository\CpuPlanRepository;
use CloudVmManager\Repository\CustomerAccountRepository;
use CloudVmManager\Repository\DiskPlanRepository;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\RamPlanRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Repository\ZoneRepository;
use wpdb;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Registers the database layer and every repository.
 */
final class DatabaseServiceProvider extends AbstractServiceProvider
{
    /**
     * Repositories sharing the same constructor signature.
     *
     * @var string[]
     */
    private const REPOSITORIES = [
        ProviderRepository::class,
        ZoneRepository::class,
        IsoTemplateRepository::class,
        CpuPlanRepository::class,
        RamPlanRepository::class,
        DiskPlanRepository::class,
        BandwidthPlanRepository::class,
        PricingRepository::class,
        VmOrderRepository::class,
        LogRepository::class,
        SyncRunRepository::class,
        CustomerAccountRepository::class,
        CacheRepository::class,
    ];

    public function register(Container $container): void
    {
        $container->singleton(
            TableRegistry::class,
            static function (Container $c): TableRegistry {
                return new TableRegistry($c->get(wpdb::class));
            }
        );

        $container->singleton(
            Schema::class,
            static function (Container $c): Schema {
                return new Schema($c->get(TableRegistry::class));
            }
        );

        $container->singleton(
            Installer::class,
            static function (Container $c): Installer {
                return new Installer(
                    $c->get(wpdb::class),
                    $c->get(Schema::class),
                    $c->get(TableRegistry::class)
                );
            }
        );

        $container->singleton(
            Migrator::class,
            static function (Container $c): Migrator {
                return new Migrator($c->get(Installer::class));
            }
        );

        foreach (self::REPOSITORIES as $repository) {
            $container->singleton(
                $repository,
                static function (Container $c) use ($repository): AbstractRepository {
                    return new $repository($c->get(wpdb::class), $c->get(TableRegistry::class));
                }
            );
        }
    }
}
