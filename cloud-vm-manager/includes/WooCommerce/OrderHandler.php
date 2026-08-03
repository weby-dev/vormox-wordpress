<?php

/**
 * WooCommerce order handling.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\VmOrderRepository;
use CloudVmManager\Service\Provisioning\ProvisioningService;
use WC_Order;
use WC_Order_Item_Product;

defined('ABSPATH') || exit;

/**
 * Turns a paid WooCommerce order into machines.
 *
 * A local row is created for every machine line the order contains, capturing
 * the identifiers the product was configured with. The configuration is copied
 * rather than referenced, so editing the product later never changes what an
 * existing order was sold.
 */
final class OrderHandler
{
    /**
     * Order meta marking that rows were already created.
     */
    private const META_PREPARED = '_cvm_prepared';

    /**
     * @var VmOrderRepository
     */
    private $orders;

    /**
     * @var ProvisioningService
     */
    private $provisioning;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        VmOrderRepository $orders,
        ProvisioningService $provisioning,
        LoggerInterface $logger
    ) {
        $this->orders = $orders;
        $this->provisioning = $provisioning;
        $this->logger = $logger;
    }

    /**
     * Attach the handler to the order lifecycle.
     */
    public function register(): void
    {
        add_action('woocommerce_checkout_order_processed', [$this, 'prepare'], 10, 1);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'prepareFromOrder'], 10, 1);
        add_action('woocommerce_payment_complete', [$this, 'fulfil'], 20, 1);
        add_action('woocommerce_order_status_processing', [$this, 'fulfil'], 20, 1);
        add_action('woocommerce_order_status_completed', [$this, 'fulfil'], 20, 1);

        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'renderOrderPanel']);
    }

    /**
     * Create the local rows of an order placed through the classic checkout.
     *
     * @param int $orderId Order that was just placed.
     */
    public function prepare($orderId): void
    {
        $order = wc_get_order((int) $orderId);

        if ($order instanceof WC_Order) {
            $this->prepareFromOrder($order);
        }
    }

    /**
     * Create the local rows of an order.
     *
     * @param WC_Order $order Order to prepare.
     *
     * @return VmOrder[] Rows belonging to the order.
     */
    public function prepareFromOrder($order): array
    {
        if (!$order instanceof WC_Order) {
            return [];
        }

        $existing = $this->orders->forWooCommerceOrder($order->get_id());

        if ($existing !== [] || $order->get_meta(self::META_PREPARED) === 'yes') {
            return $existing;
        }

        $created = [];
        $coupons = $order->get_coupon_codes();
        $couponCode = is_array($coupons) && $coupons !== [] ? (string) $coupons[0] : '';

        foreach ($order->get_items() as $itemId => $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();

            if (!$product instanceof \WC_Product || $product->get_type() !== ProductType::SLUG) {
                continue;
            }

            $configuration = ProductConfiguration::fromProduct($product);

            if (!$configuration->isComplete()) {
                $this->logger->error(
                    sprintf(
                        'Product %d is not fully configured, order %d cannot be provisioned.',
                        $product->get_id(),
                        $order->get_id()
                    ),
                    [
                        'channel' => LogEntry::CHANNEL_PROVISIONING,
                        'wc_order_id' => $order->get_id(),
                        'missing' => $configuration->missingFields(),
                    ]
                );

                continue;
            }

            $quantity = max(1, (int) $item->get_quantity());

            $rowId = $this->orders->insert(
                [
                    'wc_order_id' => $order->get_id(),
                    'wc_order_item_id' => (int) $itemId,
                    'product_id' => $product->get_id(),
                    'user_id' => (int) $order->get_customer_id(),
                    'provider_id' => $configuration->providerId(),
                    'zone_remote_id' => $configuration->zoneRemoteId(),
                    'iso_remote_id' => $configuration->isoRemoteId(),
                    'plan_type' => $configuration->planType(),
                    'cpu_price_id' => $configuration->priceId('cpu'),
                    'ram_price_id' => $configuration->priceId('ram'),
                    'disk_price_id' => $configuration->priceId('disk'),
                    'bandwidth_price_id' => $configuration->priceId('bandwidth'),
                    'months' => $configuration->months(),
                    'quantity' => $quantity,
                    'billing_cycle' => $configuration->billingCycle(),
                    'currency' => $order->get_currency(),
                    'provider_amount' => $configuration->providerCost() * $quantity,
                    'sale_amount' => (float) $item->get_total(),
                    'coupon_code' => $configuration->allowsCoupons() ? $couponCode : '',
                    'status' => VmOrder::STATUS_PENDING,
                    'provisioning_status' => VmOrder::PROVISIONING_PENDING,
                ]
            );

            $row = $this->orders->findOrder($rowId);

            if ($row !== null) {
                $created[] = $row;
            }
        }

        if ($created !== []) {
            $order->update_meta_data(self::META_PREPARED, 'yes');
            $order->save();

            $this->logger->info(
                sprintf('Order %d contains %d machine(s) to provision.', $order->get_id(), count($created)),
                [
                    'channel' => LogEntry::CHANNEL_PROVISIONING,
                    'wc_order_id' => $order->get_id(),
                    'user_id' => (int) $order->get_customer_id(),
                ]
            );
        }

        return $created;
    }

    /**
     * Provision the machines of an order once it is paid.
     *
     * @param int $orderId Order that reached a paid state.
     */
    public function fulfil($orderId): void
    {
        $order = wc_get_order((int) $orderId);

        if (!$order instanceof WC_Order) {
            return;
        }

        $rows = $this->prepareFromOrder($order);

        if ($rows === [] && $this->orders->forWooCommerceOrder($order->get_id()) === []) {
            return;
        }

        $this->provisioning->provisionWooCommerceOrder($order->get_id());
    }

    /**
     * Show the provisioning state of an order in the admin.
     *
     * @param WC_Order $order Order being displayed.
     */
    public function renderOrderPanel($order): void
    {
        if (!$order instanceof WC_Order) {
            return;
        }

        $rows = $this->orders->forWooCommerceOrder($order->get_id());

        if ($rows === []) {
            return;
        }

        echo '<div class="cvm-order-panel"><h3>' . esc_html__('Cloud machines', 'cloud-vm-manager') . '</h3><ul>';

        foreach ($rows as $row) {
            printf(
                '<li><strong>%1$s</strong> — %2$s%3$s</li>',
                esc_html($row->getHostname() !== '' ? $row->getHostname() : __('Pending', 'cloud-vm-manager')),
                esc_html($row->getProvisioningStatus()),
                $row->getIpAddress() !== '' ? ' — ' . esc_html($row->getIpAddress()) : ''
            );
        }

        echo '</ul></div>';
    }
}
