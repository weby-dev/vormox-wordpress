<?php

/**
 * Admin menu.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

use CloudVmManager\Admin\Controller\ProvidersController;
use CloudVmManager\Admin\Controller\SettingsController;
use CloudVmManager\Admin\Controller\SyncController;
use CloudVmManager\Admin\Controller\VmOrdersController;

defined('ABSPATH') || exit;

/**
 * Registers the plugin screens and reports their hook names.
 *
 * The hook names are handed to the asset loader so the stylesheet and script
 * are only enqueued where they are needed.
 */
final class Menu
{
    private const MENU_POSITION = 56;
    private const ICON = 'dashicons-cloud';

    /**
     * @var ProvidersController
     */
    private $providers;

    /**
     * @var VmOrdersController
     */
    private $vmOrders;

    /**
     * @var SyncController
     */
    private $sync;

    /**
     * @var SettingsController
     */
    private $settings;

    /**
     * @var Assets
     */
    private $assets;

    public function __construct(
        ProvidersController $providers,
        VmOrdersController $vmOrders,
        SyncController $sync,
        SettingsController $settings,
        Assets $assets
    ) {
        $this->providers = $providers;
        $this->vmOrders = $vmOrders;
        $this->sync = $sync;
        $this->settings = $settings;
        $this->assets = $assets;
    }

    /**
     * Register every screen of the plugin.
     */
    public function register(): void
    {
        $capability = Access::capability();
        $hooks = [];

        $hooks[] = add_menu_page(
            __('Cloud VM Manager', 'cloud-vm-manager'),
            __('Cloud VM', 'cloud-vm-manager'),
            $capability,
            ProvidersController::PAGE,
            [$this->providers, 'render'],
            self::ICON,
            self::MENU_POSITION
        );

        $hooks[] = add_submenu_page(
            ProvidersController::PAGE,
            __('Cloud Providers', 'cloud-vm-manager'),
            __('Providers', 'cloud-vm-manager'),
            $capability,
            ProvidersController::PAGE,
            [$this->providers, 'render']
        );

        $hooks[] = add_submenu_page(
            ProvidersController::PAGE,
            __('Virtual Machines', 'cloud-vm-manager'),
            __('Virtual Machines', 'cloud-vm-manager'),
            $capability,
            VmOrdersController::PAGE,
            [$this->vmOrders, 'render']
        );

        $hooks[] = add_submenu_page(
            ProvidersController::PAGE,
            __('Catalogue Synchronisation', 'cloud-vm-manager'),
            __('Synchronisation', 'cloud-vm-manager'),
            $capability,
            SyncController::PAGE,
            [$this->sync, 'render']
        );

        $hooks[] = add_submenu_page(
            ProvidersController::PAGE,
            __('Cloud VM Settings', 'cloud-vm-manager'),
            __('Settings', 'cloud-vm-manager'),
            $capability,
            SettingsController::PAGE,
            [$this->settings, 'render']
        );

        $this->assets->setScreens(array_values(array_filter($hooks, 'is_string')));
    }
}
