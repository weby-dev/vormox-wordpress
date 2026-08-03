<?php

/**
 * Product save handler.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce\Admin;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\WooCommerce\PriceCalculator;
use CloudVmManager\WooCommerce\ProductConfiguration;
use CloudVmManager\WooCommerce\ProductType;
use WC_Product;

defined('ABSPATH') || exit;

/**
 * Stores the machine configuration and derives the product price from it.
 *
 * WooCommerce verifies the nonce of the product form before this runs, so the
 * handler only has to validate and sanitise the submitted identifiers.
 */
final class ProductSaver
{
    /**
     * @var PriceCalculator
     */
    private $calculator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(PriceCalculator $calculator, LoggerInterface $logger)
    {
        $this->calculator = $calculator;
        $this->logger = $logger;
    }

    /**
     * Attach the handler to the product save cycle.
     */
    public function register(): void
    {
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
    }

    /**
     * Persist the configuration of a cloud virtual machine product.
     *
     * @param WC_Product $product Product being saved.
     */
    public function save($product): void
    {
        if (!$product instanceof WC_Product || $product->get_type() !== ProductType::SLUG) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the product form nonce.
        $providerId = isset($_POST['cvm_provider_id']) ? absint(wp_unslash((string) $_POST['cvm_provider_id'])) : 0;
        $zoneRemoteId = isset($_POST['cvm_zone_remote_id'])
            ? absint(wp_unslash((string) $_POST['cvm_zone_remote_id']))
            : 0;
        $isoRemoteId = isset($_POST['cvm_iso_remote_id'])
            ? absint(wp_unslash((string) $_POST['cvm_iso_remote_id']))
            : 0;
        $planType = isset($_POST['cvm_plan_type'])
            ? strtoupper(sanitize_text_field(wp_unslash((string) $_POST['cvm_plan_type'])))
            : AbstractPlan::TYPE_SHARED;
        $billingCycle = isset($_POST['cvm_billing_cycle'])
            ? sanitize_key(wp_unslash((string) $_POST['cvm_billing_cycle']))
            : '';
        $priceMode = isset($_POST['cvm_price_mode'])
            ? sanitize_key(wp_unslash((string) $_POST['cvm_price_mode']))
            : ProductType::PRICE_MODE_AUTO;
        $manualPrice = isset($_POST['cvm_manual_price'])
            ? (float) wc_format_decimal(wp_unslash((string) $_POST['cvm_manual_price']))
            : 0.0;
        $allowCoupons = !empty($_POST['cvm_allow_coupons']);

        $priceIds = [];

        foreach (PricingRule::resourceTypes() as $resource) {
            $field = 'cvm_' . $resource . '_price_id';
            $priceIds[$resource] = isset($_POST[$field]) ? absint(wp_unslash((string) $_POST[$field])) : 0;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $planType = $planType === AbstractPlan::TYPE_DEDICATED
            ? AbstractPlan::TYPE_DEDICATED
            : AbstractPlan::TYPE_SHARED;

        $billingCycle = isset(ProductType::billingCycles()[$billingCycle])
            ? $billingCycle
            : array_key_first(ProductType::billingCycles());

        $priceMode = $priceMode === ProductType::PRICE_MODE_MANUAL
            ? ProductType::PRICE_MODE_MANUAL
            : ProductType::PRICE_MODE_AUTO;

        $breakdown = $this->calculator->calculate(
            $providerId,
            $planType,
            $priceIds,
            $billingCycle,
            get_woocommerce_currency()
        );

        $price = $priceMode === ProductType::PRICE_MODE_MANUAL ? $manualPrice : $breakdown->sellingPrice();

        $product->update_meta_data(ProductType::META_PROVIDER_ID, $providerId);
        $product->update_meta_data(ProductType::META_ZONE_REMOTE_ID, $zoneRemoteId);
        $product->update_meta_data(ProductType::META_ISO_REMOTE_ID, $isoRemoteId);
        $product->update_meta_data(ProductType::META_PLAN_TYPE, $planType);
        $product->update_meta_data(ProductType::META_BILLING_CYCLE, $billingCycle);
        $product->update_meta_data(ProductType::META_PRICE_MODE, $priceMode);
        $product->update_meta_data(ProductType::META_MANUAL_PRICE, $manualPrice);
        $product->update_meta_data(ProductType::META_PROVIDER_COST, $breakdown->providerCost());
        $product->update_meta_data(ProductType::META_CURRENCY, $breakdown->currency());
        $product->update_meta_data(ProductType::META_ALLOW_COUPONS, $allowCoupons ? 'yes' : 'no');

        foreach ($priceIds as $resource => $priceId) {
            $product->update_meta_data(ProductType::priceIdMetaKey($resource), $priceId);
        }

        $product->set_virtual(true);
        $product->set_regular_price((string) $price);

        if ($product->get_sale_price('edit') === '') {
            $product->set_price((string) $price);
        }

        $configuration = new ProductConfiguration(
            [
                'provider_id' => $providerId,
                'zone_remote_id' => $zoneRemoteId,
                'iso_remote_id' => $isoRemoteId,
                'cpu' => $priceIds['cpu'],
                'ram' => $priceIds['ram'],
                'disk' => $priceIds['disk'],
                'bandwidth' => $priceIds['bandwidth'],
            ]
        );

        if (!$configuration->isComplete()) {
            $this->logger->warning(
                sprintf('Product %d is not fully configured yet.', $product->get_id()),
                [
                    'channel' => LogEntry::CHANNEL_ADMIN,
                    'product_id' => $product->get_id(),
                    'missing' => $configuration->missingFields(),
                ]
            );
        }
    }
}
