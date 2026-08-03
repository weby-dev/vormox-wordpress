<?php

/**
 * Frontend service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Admin\View;
use CloudVmManager\Ajax\DashboardAjaxController;
use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Frontend\Dashboard;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Service\Vm\VmMetricsService;
use CloudVmManager\Service\Vm\VmService;
use CloudVmManager\Service\Vm\WalletService;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Registers the customer dashboard and the services it reads from.
 */
final class FrontendServiceProvider extends AbstractServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            VmService::class,
            static function (Container $c): VmService {
                return new VmService(
                    $c->get(VmOrderRepository::class),
                    $c->get(ProviderRepository::class),
                    $c->get(ProviderGateway::class)
                );
            }
        );

        $container->singleton(
            VmMetricsService::class,
            static function (Container $c): VmMetricsService {
                return new VmMetricsService(
                    $c->get(ProviderGateway::class),
                    $c->get(VmService::class),
                    $c->get(Cache::class)
                );
            }
        );

        $container->singleton(
            WalletService::class,
            static function (Container $c): WalletService {
                return new WalletService(
                    $c->get(ProviderGateway::class),
                    $c->get(ProviderRepository::class)
                );
            }
        );

        $container->singleton(
            Dashboard::class,
            static function (Container $c): Dashboard {
                return new Dashboard(
                    $c->get(VmService::class),
                    $c->get(VmMetricsService::class),
                    $c->get(WalletService::class),
                    $c->get(LogRepository::class),
                    $c->get(Settings::class),
                    $c->get(View::class)
                );
            }
        );

        $container->singleton(
            DashboardAjaxController::class,
            static function (Container $c): DashboardAjaxController {
                return new DashboardAjaxController(
                    $c->get(VmService::class),
                    $c->get(VmMetricsService::class),
                    $c->get(WalletService::class)
                );
            }
        );
    }

    public function boot(Container $container): void
    {
        /** @var Dashboard $dashboard */
        $dashboard = $container->get(Dashboard::class);
        $dashboard->register();

        /** @var DashboardAjaxController $ajax */
        $ajax = $container->get(DashboardAjaxController::class);
        $ajax->register();
    }
}
