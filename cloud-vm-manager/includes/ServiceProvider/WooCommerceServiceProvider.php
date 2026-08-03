<?php

/**
 * WooCommerce service provider.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\ServiceProvider;

use CloudVmManager\Admin\Access;
use CloudVmManager\Admin\View;
use CloudVmManager\Ajax\ProductAjaxController;
use CloudVmManager\Bootstrap\Requirements;
use CloudVmManager\Container\AbstractServiceProvider;
use CloudVmManager\Container\Container;
use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Repository\ZoneRepository;
use CloudVmManager\Service\Provider\ProviderService;
use CloudVmManager\WooCommerce\Admin\ProductDataPanel;
use CloudVmManager\WooCommerce\Admin\ProductSaver;
use CloudVmManager\WooCommerce\PriceCalculator;
use CloudVmManager\WooCommerce\ProductType;
use CloudVmManager\WooCommerce\ProductTypeRegistrar;

defined('ABSPATH') || exit;

/**
 * Registers the cloud virtual machine product type and its admin surface.
 *
 * Nothing here boots unless WooCommerce is active and of a supported version.
 */
final class WooCommerceServiceProvider extends AbstractServiceProvider
{
    public const ASSET_HANDLE = 'cloud-vm-manager-product';

    public function register(Container $container): void
    {
        $container->singleton(
            PriceCalculator::class,
            static function (Container $c): PriceCalculator {
                return new PriceCalculator($c->get(PricingRepository::class));
            }
        );

        $container->singleton(
            ProductTypeRegistrar::class,
            static function (): ProductTypeRegistrar {
                return new ProductTypeRegistrar();
            }
        );

        $container->singleton(
            ProductDataPanel::class,
            static function (Container $c): ProductDataPanel {
                return new ProductDataPanel(
                    $c->get(ProviderService::class),
                    $c->get(ZoneRepository::class),
                    $c->get(IsoTemplateRepository::class),
                    $c->get(PricingRepository::class),
                    $c->get(PriceCalculator::class),
                    $c->get(View::class)
                );
            }
        );

        $container->singleton(
            ProductSaver::class,
            static function (Container $c): ProductSaver {
                return new ProductSaver(
                    $c->get(PriceCalculator::class),
                    $c->get(LoggerInterface::class)
                );
            }
        );

        $container->singleton(
            ProductAjaxController::class,
            static function (Container $c): ProductAjaxController {
                return new ProductAjaxController($c->get(ProductDataPanel::class));
            }
        );
    }

    public function boot(Container $container): void
    {
        $requirements = new Requirements();

        if (!$requirements->hasWooCommerce()) {
            return;
        }

        /** @var ProductTypeRegistrar $registrar */
        $registrar = $container->get(ProductTypeRegistrar::class);
        $registrar->register();

        add_action('init', [$this, 'registerProductTypeTerm'], 5);

        if (!is_admin()) {
            return;
        }

        /** @var ProductDataPanel $panel */
        $panel = $container->get(ProductDataPanel::class);
        $panel->register();

        /** @var ProductSaver $saver */
        $saver = $container->get(ProductSaver::class);
        $saver->register();

        /** @var ProductAjaxController $ajax */
        $ajax = $container->get(ProductAjaxController::class);
        $ajax->register();

        add_action('admin_enqueue_scripts', [$this, 'enqueueProductAssets']);
    }

    /**
     * Make sure the product type term exists so products can be classified.
     */
    public function registerProductTypeTerm(): void
    {
        if (!taxonomy_exists('product_type')) {
            return;
        }

        if (term_exists(ProductType::SLUG, 'product_type') === null) {
            wp_insert_term(ProductType::SLUG, 'product_type');
        }
    }

    /**
     * Load the product panel script on the product edit screen only.
     */
    public function enqueueProductAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();

        if ($screen === null || $screen->post_type !== 'product') {
            return;
        }

        wp_enqueue_style(
            self::ASSET_HANDLE,
            CVM_PLUGIN_URL . 'assets/css/product-admin.css',
            [],
            CVM_VERSION
        );

        wp_enqueue_script(
            self::ASSET_HANDLE,
            CVM_PLUGIN_URL . 'assets/js/product-admin.js',
            [],
            CVM_VERSION,
            true
        );

        wp_localize_script(
            self::ASSET_HANDLE,
            'cvmProduct',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(Access::AJAX_NONCE),
                'months' => VmOrder::billingCycles(),
                'i18n' => [
                    'selectZone' => __('— Select a zone —', 'cloud-vm-manager'),
                    'selectImage' => __('— Select an image —', 'cloud-vm-manager'),
                    'selectTier' => __('— Select a tier —', 'cloud-vm-manager'),
                ],
            ]
        );
    }
}
