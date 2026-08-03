<?php

/**
 * Admin service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Admin\Assets;
use CloudVmManager\Admin\Controller\ProvidersController;
use CloudVmManager\Admin\Controller\SettingsController;
use CloudVmManager\Admin\Menu;
use CloudVmManager\Admin\Notices;
use CloudVmManager\Admin\SettingsFields;
use CloudVmManager\Admin\View;
use CloudVmManager\Ajax\ProviderAjaxController;
use CloudVmManager\Bootstrap\Requirements;
use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Cron\CronManager;
use CloudVmManager\Service\Provider\ConnectionTester;
use CloudVmManager\Service\Provider\ProviderAuthenticator;
use CloudVmManager\Service\Provider\ProviderService;
use CloudVmManager\Support\Settings;

defined('ABSPATH') || exit;

/**
 * Registers the admin screens, their form handlers and the AJAX endpoints.
 *
 * Nothing here is instantiated on a frontend request: the whole provider only
 * wires itself up inside `wp-admin` and during AJAX calls.
 */
final class AdminServiceProvider extends AbstractServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(
            View::class,
            static function (): View {
                return new View(CVM_PLUGIN_DIR . 'templates');
            }
        );

        $container->singleton(
            Notices::class,
            static function (): Notices {
                return new Notices();
            }
        );

        $container->singleton(
            Assets::class,
            static function (): Assets {
                return new Assets();
            }
        );

        $container->singleton(
            SettingsFields::class,
            static function (): SettingsFields {
                return new SettingsFields();
            }
        );

        $container->singleton(
            ProvidersController::class,
            static function (Container $c): ProvidersController {
                return new ProvidersController(
                    $c->get(ProviderService::class),
                    $c->get(View::class),
                    $c->get(Notices::class)
                );
            }
        );

        $container->singleton(
            SettingsController::class,
            static function (Container $c): SettingsController {
                return new SettingsController(
                    $c->get(Settings::class),
                    $c->get(SettingsFields::class),
                    $c->get(CronManager::class),
                    $c->get(View::class),
                    $c->get(Notices::class)
                );
            }
        );

        $container->singleton(
            Menu::class,
            static function (Container $c): Menu {
                return new Menu(
                    $c->get(ProvidersController::class),
                    $c->get(SettingsController::class),
                    $c->get(Assets::class)
                );
            }
        );

        $container->singleton(
            ProviderAjaxController::class,
            static function (Container $c): ProviderAjaxController {
                return new ProviderAjaxController(
                    $c->get(ProviderService::class),
                    $c->get(ConnectionTester::class),
                    $c->get(ProviderAuthenticator::class)
                );
            }
        );
    }

    public function boot(Container $container): void
    {
        if (!is_admin()) {
            return;
        }

        /** @var Menu $menu */
        $menu = $container->get(Menu::class);
        add_action('admin_menu', [$menu, 'register']);

        /** @var Assets $assets */
        $assets = $container->get(Assets::class);
        add_action('admin_enqueue_scripts', [$assets, 'enqueue']);

        /** @var Notices $notices */
        $notices = $container->get(Notices::class);
        add_action('admin_notices', [$notices, 'render']);

        /** @var ProvidersController $providers */
        $providers = $container->get(ProvidersController::class);
        add_action('admin_post_' . ProvidersController::ACTION_SAVE, [$providers, 'handleSave']);
        add_action('admin_post_' . ProvidersController::ACTION_DELETE, [$providers, 'handleDelete']);

        /** @var SettingsController $settings */
        $settings = $container->get(SettingsController::class);
        add_action('admin_post_' . SettingsController::ACTION_SAVE, [$settings, 'handleSave']);

        /** @var ProviderAjaxController $ajax */
        $ajax = $container->get(ProviderAjaxController::class);
        $ajax->register();

        $this->registerWooCommerceNotice();
    }

    /**
     * Warn once when WooCommerce is missing, since store features stay disabled.
     */
    private function registerWooCommerceNotice(): void
    {
        $requirements = new Requirements();

        if ($requirements->hasWooCommerce()) {
            return;
        }

        add_action(
            'admin_notices',
            static function () use ($requirements): void {
                if (!current_user_can('activate_plugins')) {
                    return;
                }

                printf(
                    '<div class="notice notice-warning"><p>%s</p></div>',
                    esc_html($requirements->wooCommerceNotice())
                );
            }
        );
    }
}
