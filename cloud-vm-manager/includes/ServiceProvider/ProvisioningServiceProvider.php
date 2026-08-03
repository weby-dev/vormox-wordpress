<?php

/**
 * Provisioning service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Bootstrap\Requirements;
use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Service\Provisioning\GatewayResolver;
use CloudVmManager\Service\Provisioning\ProvisioningService;
use CloudVmManager\Service\Provisioning\VmProvisioner;
use CloudVmManager\Service\Provisioning\VmResolver;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;
use CloudVmManager\WooCommerce\OrderHandler;

defined('ABSPATH') || exit;

/**
 * Registers the provisioning engine and hooks it into the order lifecycle.
 */
final class ProvisioningServiceProvider extends AbstractServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            GatewayResolver::class,
            static function (Container $c): GatewayResolver {
                return new GatewayResolver(
                    $c->get(ProviderGateway::class),
                    $c->get(Settings::class),
                    $c->get(Cache::class)
                );
            }
        );

        $container->singleton(
            VmResolver::class,
            static function (Container $c): VmResolver {
                return new VmResolver($c->get(ProviderGateway::class));
            }
        );

        $container->singleton(
            VmProvisioner::class,
            static function (Container $c): VmProvisioner {
                return new VmProvisioner(
                    $c->get(ProviderGateway::class),
                    $c->get(GatewayResolver::class),
                    $c->get(VmOrderRepository::class),
                    $c->get(Settings::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );

        $container->singleton(
            ProvisioningService::class,
            static function (Container $c): ProvisioningService {
                return new ProvisioningService(
                    $c->get(VmProvisioner::class),
                    $c->get(VmResolver::class),
                    $c->get(VmOrderRepository::class),
                    $c->get(ProviderRepository::class),
                    $c->get(Settings::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );

        $container->singleton(
            OrderHandler::class,
            static function (Container $c): OrderHandler {
                return new OrderHandler(
                    $c->get(VmOrderRepository::class),
                    $c->get(ProvisioningService::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );
    }

    public function boot(Container $container): void
    {
        $requirements = new Requirements();

        if (!$requirements->hasWooCommerce()) {
            return;
        }

        /** @var OrderHandler $handler */
        $handler = $container->get(OrderHandler::class);
        $handler->register();
    }
}
