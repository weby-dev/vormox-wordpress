<?php

/**
 * Product data panel.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce\Admin;

use CloudVmManager\Admin\View;
use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Repository\IsoTemplateRepository;
use CloudVmManager\Repository\PricingRepository;
use CloudVmManager\Repository\ZoneRepository;
use CloudVmManager\Service\Provider\ProviderService;
use CloudVmManager\WooCommerce\PriceCalculator;
use CloudVmManager\WooCommerce\ProductConfiguration;
use CloudVmManager\WooCommerce\ProductType;

defined('ABSPATH') || exit;

/**
 * Renders the machine configuration tab on the product edit screen.
 *
 * Every selector is filled from the synchronised catalogue, so an administrator
 * can only pick identifiers the backend actually offers.
 */
final class ProductDataPanel
{
    /**
     * @var ProviderService
     */
    private $providers;

    /**
     * @var ZoneRepository
     */
    private $zones;

    /**
     * @var IsoTemplateRepository
     */
    private $isos;

    /**
     * @var PricingRepository
     */
    private $pricing;

    /**
     * @var PriceCalculator
     */
    private $calculator;

    /**
     * @var View
     */
    private $view;

    public function __construct(
        ProviderService $providers,
        ZoneRepository $zones,
        IsoTemplateRepository $isos,
        PricingRepository $pricing,
        PriceCalculator $calculator,
        View $view
    ) {
        $this->providers = $providers;
        $this->zones = $zones;
        $this->isos = $isos;
        $this->pricing = $pricing;
        $this->calculator = $calculator;
        $this->view = $view;
    }

    /**
     * Attach the panel to the product edit screen.
     */
    public function register(): void
    {
        add_filter('woocommerce_product_data_tabs', [$this, 'addTab']);
        add_action('woocommerce_product_data_panels', [$this, 'renderPanel']);
    }

    /**
     * Add the configuration tab, and hide the tabs that do not apply.
     *
     * @param array<string, array<string, mixed>> $tabs
     *
     * @return array<string, array<string, mixed>>
     */
    public function addTab($tabs): array
    {
        $tabs = is_array($tabs) ? $tabs : [];

        $tabs['cloud_vm'] = [
            'label' => __('Virtual machine', 'cloud-vm-manager'),
            'target' => 'cvm_product_data',
            'class' => ['show_if_' . ProductType::SLUG],
            'priority' => 11,
        ];

        foreach (['shipping', 'attribute', 'linked_product'] as $hidden) {
            if (isset($tabs[$hidden]['class']) && is_array($tabs[$hidden]['class'])) {
                $tabs[$hidden]['class'][] = 'hide_if_' . ProductType::SLUG;
            }
        }

        if (isset($tabs['inventory']['class']) && is_array($tabs['inventory']['class'])) {
            $tabs['inventory']['class'][] = 'show_if_' . ProductType::SLUG;
        }

        return $tabs;
    }

    /**
     * Print the configuration panel.
     */
    public function renderPanel(): void
    {
        global $post;

        $productId = $post instanceof \WP_Post ? (int) $post->ID : 0;
        $product = $productId > 0 ? wc_get_product($productId) : null;

        $configuration = $product instanceof \WC_Product
            ? ProductConfiguration::fromProduct($product)
            : new ProductConfiguration();

        $providers = $this->providers->all();
        $providerId = $configuration->providerId();

        if ($providerId <= 0 && $providers !== []) {
            $providerId = $providers[0]->id();
        }

        $this->view->render(
            'admin/product/panel',
            [
                'configuration' => $configuration,
                'providers' => $providers,
                'zones' => $this->zoneOptions($providerId),
                'isos' => $this->isoOptions($providerId, $configuration->zoneRemoteId()),
                'plans' => $this->planOptions($providerId, $configuration->planType()),
                'billingCycles' => ProductType::billingCycles(),
                'planTypes' => [
                    AbstractPlan::TYPE_SHARED => __('Shared', 'cloud-vm-manager'),
                    AbstractPlan::TYPE_DEDICATED => __('Dedicated', 'cloud-vm-manager'),
                ],
                'breakdown' => $this->calculator->calculateForProduct($configuration),
                'currency' => get_woocommerce_currency(),
            ]
        );
    }

    /**
     * Zones of a provider, as select options.
     *
     * @return array<int, string>
     */
    public function zoneOptions(int $providerId): array
    {
        $options = [];

        foreach ($this->zones->forProvider($providerId) as $zone) {
            $label = $zone->getName();

            if ($zone->getCountry() !== '') {
                $label .= ' (' . $zone->getCountry() . ')';
            }

            $options[$zone->getRemoteId()] = $label;
        }

        return $options;
    }

    /**
     * Operating system images of a zone, as select options.
     *
     * @return array<int, string>
     */
    public function isoOptions(int $providerId, int $zoneRemoteId): array
    {
        $options = [];

        if ($zoneRemoteId <= 0) {
            return $options;
        }

        foreach ($this->isos->forZone($providerId, $zoneRemoteId) as $iso) {
            $options[$iso->getRemoteId()] = $iso->getIsoName();
        }

        return $options;
    }

    /**
     * Price book entries per resource, as select options with their price.
     *
     * @return array<string, array<int, array{label: string, provider: float, selling: float}>>
     */
    public function planOptions(int $providerId, string $planType): array
    {
        $options = [];

        foreach (PricingRule::resourceTypes() as $resource) {
            $options[$resource] = [];

            foreach ($this->pricing->forResource($providerId, $resource, $planType) as $rule) {
                $options[$resource][$rule->getRemotePriceId()] = [
                    'label' => $rule->getLabel(),
                    'provider' => $rule->getProviderPrice(),
                    'selling' => $rule->getSellingPrice(),
                ];
            }
        }

        return $options;
    }
}
