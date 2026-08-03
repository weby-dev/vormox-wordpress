<?php

/**
 * HTTP service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Contracts\EncryptorInterface;
use CloudVmManager\Contracts\HttpClientInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Http\ApiClient;
use CloudVmManager\Repository\ProviderRepository;
use CloudVmManager\Service\Provider\ConnectionTester;
use CloudVmManager\Service\Provider\ProviderAuthenticator;
use CloudVmManager\Service\Provider\ProviderGateway;
use CloudVmManager\Service\Provider\ProviderService;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Registers the API client and the provider services built on it.
 */
final class HttpServiceProvider extends AbstractServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            ApiClient::class,
            static function (Container $c): ApiClient {
                return new ApiClient($c->get(LoggerInterface::class), $c->get(Settings::class));
            }
        );

        $container->alias(HttpClientInterface::class, ApiClient::class);

        $container->singleton(
            ProviderAuthenticator::class,
            static function (Container $c): ProviderAuthenticator {
                return new ProviderAuthenticator(
                    $c->get(HttpClientInterface::class),
                    $c->get(ProviderRepository::class),
                    $c->get(EncryptorInterface::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );

        $container->singleton(
            ProviderGateway::class,
            static function (Container $c): ProviderGateway {
                return new ProviderGateway(
                    $c->get(HttpClientInterface::class),
                    $c->get(ProviderAuthenticator::class),
                    $c->get(ProviderRepository::class)
                );
            }
        );

        $container->singleton(
            ConnectionTester::class,
            static function (Container $c): ConnectionTester {
                return new ConnectionTester(
                    $c->get(ProviderGateway::class),
                    $c->get(ProviderAuthenticator::class),
                    $c->get(ProviderRepository::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );

        $container->singleton(
            ProviderService::class,
            static function (Container $c): ProviderService {
                return new ProviderService(
                    $c->get(ProviderRepository::class),
                    $c->get(EncryptorInterface::class),
                    $c->get(ProviderAuthenticator::class),
                    $c->get(Cache::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );
    }
}
