<?php

/**
 * Plugin runtime.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager;

use CloudVmManager\Container\Container;
use CloudVmManager\Container\ServiceProviderInterface;
use CloudVmManager\Database\Migrator;
use CloudVmManager\ServiceProvider\AdminServiceProvider;
use CloudVmManager\ServiceProvider\CoreServiceProvider;
use CloudVmManager\ServiceProvider\CronServiceProvider;
use CloudVmManager\ServiceProvider\DatabaseServiceProvider;
use CloudVmManager\ServiceProvider\HttpServiceProvider;
use CloudVmManager\ServiceProvider\SyncServiceProvider;
use wpdb;

defined('ABSPATH') || exit;

/**
 * Central runtime object.
 *
 * Owns the dependency injection container, registers the service providers and
 * boots them. It deliberately contains no business logic.
 */
final class Plugin
{
    /**
     * @var self|null
     */
    private static $instance;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var ServiceProviderInterface[]
     */
    private $providers = [];

    /**
     * @var bool
     */
    private $registered = false;

    /**
     * @var bool
     */
    private $booted = false;

    private function __construct()
    {
        $this->container = new Container();
    }

    /**
     * Prevent cloning of the runtime singleton.
     */
    private function __clone()
    {
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Register every service into the container without touching WordPress hooks.
     *
     * Safe to call during plugin activation, deactivation and uninstall where the
     * regular boot sequence never runs.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->registered = true;

        global $wpdb;

        $this->container->instance(Container::class, $this->container);
        $this->container->instance(self::class, $this);
        $this->container->instance(wpdb::class, $wpdb);

        foreach ($this->providerClasses() as $providerClass) {
            if (!class_exists($providerClass) || !is_subclass_of($providerClass, ServiceProviderInterface::class)) {
                continue;
            }

            /** @var ServiceProviderInterface $provider */
            $provider = new $providerClass();
            $provider->register($this->container);

            $this->providers[] = $provider;
        }
    }

    /**
     * Register services, run pending database migrations and boot every provider.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->register();
        $this->booted = true;

        /** @var Migrator $migrator */
        $migrator = $this->container->get(Migrator::class);
        $migrator->maybeUpgrade();

        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        add_action('init', [$this, 'loadTextdomain'], 0);

        /**
         * Fires once every plugin service has been booted.
         *
         * @param Container $container Dependency injection container.
         */
        do_action('cloud_vm_manager_booted', $this->container);
    }

    /**
     * Load the plugin translations.
     */
    public function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'cloud-vm-manager',
            false,
            dirname(CVM_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Service providers that make up the plugin.
     *
     * @return string[]
     */
    private function providerClasses(): array
    {
        $providers = [
            CoreServiceProvider::class,
            DatabaseServiceProvider::class,
            HttpServiceProvider::class,
            SyncServiceProvider::class,
            CronServiceProvider::class,
            AdminServiceProvider::class,
        ];

        /**
         * Filter the registered service providers.
         *
         * @param string[] $providers Fully qualified service provider class names.
         */
        $filtered = apply_filters('cloud_vm_manager_service_providers', $providers);

        return is_array($filtered) ? $filtered : $providers;
    }
}
