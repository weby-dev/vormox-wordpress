<?php

/**
 * Core service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Contracts\CacheInterface;
use CloudVmManager\Contracts\EncryptorInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Logging\Logger;
use CloudVmManager\Logging\Redactor;
use CloudVmManager\Repository\CacheRepository;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Encryptor;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Registers settings, encryption, logging and caching.
 */
final class CoreServiceProvider extends AbstractServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            Settings::class,
            static function (): Settings {
                return new Settings();
            }
        );

        $container->singleton(
            Encryptor::class,
            static function (): Encryptor {
                return new Encryptor();
            }
        );

        $container->singleton(
            Redactor::class,
            static function (): Redactor {
                return new Redactor();
            }
        );

        $container->singleton(
            Logger::class,
            static function (Container $c): Logger {
                return new Logger(
                    $c->get(LogRepository::class),
                    $c->get(Settings::class),
                    $c->get(Redactor::class)
                );
            }
        );

        $container->singleton(
            Cache::class,
            static function (Container $c): Cache {
                return new Cache(
                    $c->get(CacheRepository::class),
                    $c->get(Settings::class)
                );
            }
        );

        $container->alias(EncryptorInterface::class, Encryptor::class);
        $container->alias(LoggerInterface::class, Logger::class);
        $container->alias(CacheInterface::class, Cache::class);
    }
}
