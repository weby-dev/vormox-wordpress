<?php

/**
 * Cron service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Contracts\JobInterface;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Cron\CronManager;
use CloudVmManager\Cron\MaintenanceJob;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Repository\SyncRunRepository;
use CloudVmManager\Support\Cache;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Registers the scheduled jobs of the plugin.
 */
final class CronServiceProvider extends AbstractServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            MaintenanceJob::class,
            static function (Container $c): MaintenanceJob {
                return new MaintenanceJob(
                    $c->get(Cache::class),
                    $c->get(LogRepository::class),
                    $c->get(SyncRunRepository::class),
                    $c->get(Settings::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );

        $container->singleton(
            CronManager::class,
            static function (Container $c): CronManager {
                $manager = new CronManager($c->get(LoggerInterface::class));

                foreach (self::jobClasses() as $jobClass) {
                    $job = $c->get($jobClass);

                    if ($job instanceof JobInterface) {
                        $manager->addJob($job);
                    }
                }

                return $manager;
            }
        );
    }

    public function boot(Container $container): void
    {
        /** @var CronManager $manager */
        $manager = $container->get(CronManager::class);
        $manager->registerHooks();

        add_action(
            'init',
            static function () use ($manager): void {
                $manager->scheduleAll();
            },
            20
        );
    }

    /**
     * Job classes managed by the cron manager.
     *
     * @return string[]
     */
    private static function jobClasses(): array
    {
        $jobs = [MaintenanceJob::class];

        /**
         * Filter the registered cron jobs.
         *
         * @param string[] $jobs Fully qualified job class names.
         */
        $filtered = apply_filters('cloud_vm_manager_cron_jobs', $jobs);

        return is_array($filtered) ? $filtered : $jobs;
    }
}
