<?php

/**
 * Product configuration.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Model\VmOrder;
use WC_Product;

defined('ABSPATH') || exit;

/**
 * The machine a product sells, read from and written to product meta.
 *
 * Only backend identifiers are stored. Nothing here holds a name or a price
 * that the provisioning call could accidentally submit.
 */
final class ProductConfiguration
{
    /**
     * @var array<string, mixed>
     */
    private $values;

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        $this->values = $values;
    }

    /**
     * Read the configuration stored on a product.
     */
    public static function fromProduct(WC_Product $product): self
    {
        $values = [];

        foreach (self::metaKeys() as $field => $metaKey) {
            $values[$field] = $product->get_meta($metaKey, true);
        }

        return new self($values);
    }

    /**
     * Field name to meta key map.
     *
     * @return array<string, string>
     */
    public static function metaKeys(): array
    {
        return [
            'provider_id' => ProductType::META_PROVIDER_ID,
            'zone_remote_id' => ProductType::META_ZONE_REMOTE_ID,
            'iso_remote_id' => ProductType::META_ISO_REMOTE_ID,
            'plan_type' => ProductType::META_PLAN_TYPE,
            'cpu' => ProductType::META_CPU_PRICE_ID,
            'ram' => ProductType::META_RAM_PRICE_ID,
            'disk' => ProductType::META_DISK_PRICE_ID,
            'bandwidth' => ProductType::META_BANDWIDTH_PRICE_ID,
            'billing_cycle' => ProductType::META_BILLING_CYCLE,
            'price_mode' => ProductType::META_PRICE_MODE,
            'manual_price' => ProductType::META_MANUAL_PRICE,
            'provider_cost' => ProductType::META_PROVIDER_COST,
            'currency' => ProductType::META_CURRENCY,
            'allow_coupons' => ProductType::META_ALLOW_COUPONS,
        ];
    }

    public function providerId(): int
    {
        return (int) ($this->values['provider_id'] ?? 0);
    }

    public function zoneRemoteId(): int
    {
        return (int) ($this->values['zone_remote_id'] ?? 0);
    }

    public function isoRemoteId(): int
    {
        return (int) ($this->values['iso_remote_id'] ?? 0);
    }

    public function planType(): string
    {
        $planType = strtoupper((string) ($this->values['plan_type'] ?? AbstractPlan::TYPE_SHARED));

        return $planType === AbstractPlan::TYPE_DEDICATED
            ? AbstractPlan::TYPE_DEDICATED
            : AbstractPlan::TYPE_SHARED;
    }

    /**
     * Backend pricing identifier of one resource.
     */
    public function priceId(string $resource): int
    {
        return (int) ($this->values[$resource] ?? 0);
    }

    /**
     * Backend pricing identifiers of every resource.
     *
     * @return array<string, int>
     */
    public function priceIds(): array
    {
        $ids = [];

        foreach (PricingRule::resourceTypes() as $resource) {
            $ids[$resource] = $this->priceId($resource);
        }

        return $ids;
    }

    public function billingCycle(): string
    {
        $cycle = (string) ($this->values['billing_cycle'] ?? VmOrder::CYCLE_MONTHLY);

        return isset(ProductType::billingCycles()[$cycle]) ? $cycle : VmOrder::CYCLE_MONTHLY;
    }

    public function months(): int
    {
        return VmOrder::monthsForCycle($this->billingCycle());
    }

    public function priceMode(): string
    {
        return ((string) ($this->values['price_mode'] ?? '')) === ProductType::PRICE_MODE_MANUAL
            ? ProductType::PRICE_MODE_MANUAL
            : ProductType::PRICE_MODE_AUTO;
    }

    public function isManuallyPriced(): bool
    {
        return $this->priceMode() === ProductType::PRICE_MODE_MANUAL;
    }

    public function manualPrice(): float
    {
        return (float) ($this->values['manual_price'] ?? 0);
    }

    public function providerCost(): float
    {
        return (float) ($this->values['provider_cost'] ?? 0);
    }

    public function currency(): string
    {
        return (string) ($this->values['currency'] ?? '');
    }

    public function allowsCoupons(): bool
    {
        return !empty($this->values['allow_coupons']);
    }

    /**
     * Whether every identifier needed to provision is present.
     */
    public function isComplete(): bool
    {
        if ($this->providerId() <= 0 || $this->zoneRemoteId() <= 0 || $this->isoRemoteId() <= 0) {
            return false;
        }

        foreach ($this->priceIds() as $priceId) {
            if ($priceId <= 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fields that are still missing, for the admin notice.
     *
     * @return string[]
     */
    public function missingFields(): array
    {
        $missing = [];

        if ($this->providerId() <= 0) {
            $missing[] = __('provider', 'cloud-vm-manager');
        }

        if ($this->zoneRemoteId() <= 0) {
            $missing[] = __('zone', 'cloud-vm-manager');
        }

        if ($this->isoRemoteId() <= 0) {
            $missing[] = __('operating system', 'cloud-vm-manager');
        }

        foreach ($this->priceIds() as $resource => $priceId) {
            if ($priceId <= 0) {
                $missing[] = $resource;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
