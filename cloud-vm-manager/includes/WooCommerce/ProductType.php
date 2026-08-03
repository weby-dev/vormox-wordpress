<?php

/**
 * Product type constants.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

use CloudVmManager\Model\VmOrder;

defined('ABSPATH') || exit;

/**
 * Identity and meta keys of the cloud virtual machine product type.
 *
 * Every key is declared once here so the admin panel, the order handler and the
 * provisioning engine cannot drift apart.
 */
final class ProductType
{
    /**
     * Slug WooCommerce identifies the product type by.
     */
    public const SLUG = 'cloud_vm';

    public const META_PROVIDER_ID = '_cvm_provider_id';
    public const META_ZONE_REMOTE_ID = '_cvm_zone_remote_id';
    public const META_ISO_REMOTE_ID = '_cvm_iso_remote_id';
    public const META_PLAN_TYPE = '_cvm_plan_type';
    public const META_CPU_PRICE_ID = '_cvm_cpu_price_id';
    public const META_RAM_PRICE_ID = '_cvm_ram_price_id';
    public const META_DISK_PRICE_ID = '_cvm_disk_price_id';
    public const META_BANDWIDTH_PRICE_ID = '_cvm_bandwidth_price_id';
    public const META_BILLING_CYCLE = '_cvm_billing_cycle';
    public const META_PRICE_MODE = '_cvm_price_mode';
    public const META_MANUAL_PRICE = '_cvm_manual_price';
    public const META_PROVIDER_COST = '_cvm_provider_cost';
    public const META_CURRENCY = '_cvm_currency';
    public const META_ALLOW_COUPONS = '_cvm_allow_coupons';

    public const PRICE_MODE_AUTO = 'auto';
    public const PRICE_MODE_MANUAL = 'manual';

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Human readable label of the product type.
     */
    public static function label(): string
    {
        return __('Cloud Virtual Machine', 'cloud-vm-manager');
    }

    /**
     * Meta key holding the pricing identifier of a resource.
     */
    public static function priceIdMetaKey(string $resource): string
    {
        $keys = [
            'cpu' => self::META_CPU_PRICE_ID,
            'ram' => self::META_RAM_PRICE_ID,
            'disk' => self::META_DISK_PRICE_ID,
            'bandwidth' => self::META_BANDWIDTH_PRICE_ID,
        ];

        return $keys[$resource] ?? '';
    }

    /**
     * Billing cycles offered by the product, labelled for the admin.
     *
     * @return array<string, string>
     */
    public static function billingCycles(): array
    {
        return [
            VmOrder::CYCLE_MONTHLY => __('Monthly', 'cloud-vm-manager'),
            VmOrder::CYCLE_QUARTERLY => __('Quarterly', 'cloud-vm-manager'),
            VmOrder::CYCLE_SEMI_ANNUAL => __('Semi annual', 'cloud-vm-manager'),
            VmOrder::CYCLE_ANNUAL => __('Annual', 'cloud-vm-manager'),
        ];
    }
}
