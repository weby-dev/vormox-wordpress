<?php

/**
 * Pricing rule model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Local price book entry.
 *
 * Links a backend pricing identifier to the price the store charges. The backend
 * price is never sent back to the API: provisioning always submits identifiers.
 */
final class PricingRule extends AbstractModel
{
    public const RESOURCE_CPU = 'cpu';
    public const RESOURCE_RAM = 'ram';
    public const RESOURCE_DISK = 'disk';
    public const RESOURCE_BANDWIDTH = 'bandwidth';

    public const MARKUP_PERCENT = 'percent';
    public const MARKUP_FIXED = 'fixed';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';

    /**
     * Every supported resource type.
     *
     * @return string[]
     */
    public static function resourceTypes(): array
    {
        return [
            self::RESOURCE_CPU,
            self::RESOURCE_RAM,
            self::RESOURCE_DISK,
            self::RESOURCE_BANDWIDTH,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'provider_id' => 'int',
            'resource_type' => 'string',
            'plan_type' => 'string',
            'remote_price_id' => 'int',
            'plan_id' => 'int',
            'label' => 'string',
            'provider_price' => 'float',
            'markup_type' => 'string',
            'markup_value' => 'float',
            'selling_price' => 'float',
            'currency' => 'string',
            'is_manual' => 'bool',
            'status' => 'string',
            'synced_at' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
        ];
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    public function getResourceType(): string
    {
        return $this->getString('resource_type');
    }

    public function getPlanType(): string
    {
        return $this->getString('plan_type', AbstractPlan::TYPE_SHARED);
    }

    /**
     * Backend pricing identifier submitted when creating or upgrading a machine.
     */
    public function getRemotePriceId(): int
    {
        return $this->getInt('remote_price_id');
    }

    public function getLabel(): string
    {
        return $this->getString('label');
    }

    public function getProviderPrice(): float
    {
        return $this->getFloat('provider_price');
    }

    public function getMarkupType(): string
    {
        return $this->getString('markup_type', self::MARKUP_PERCENT);
    }

    public function getMarkupValue(): float
    {
        return $this->getFloat('markup_value');
    }

    /**
     * Price charged to the customer. Manual prices win over the calculated markup.
     */
    public function getSellingPrice(): float
    {
        if ($this->isManual()) {
            return $this->getFloat('selling_price');
        }

        return $this->calculateSellingPrice();
    }

    /**
     * Apply the markup to the provider price.
     */
    public function calculateSellingPrice(): float
    {
        $base = $this->getProviderPrice();
        $markup = $this->getMarkupValue();

        if ($this->getMarkupType() === self::MARKUP_FIXED) {
            return round($base + $markup, 4);
        }

        return round($base + ($base * $markup / 100), 4);
    }

    /**
     * Absolute profit per billing month.
     */
    public function getProfit(): float
    {
        return round($this->getSellingPrice() - $this->getProviderPrice(), 4);
    }

    /**
     * Profit margin of the selling price, expressed as a percentage.
     */
    public function getProfitMargin(): float
    {
        $selling = $this->getSellingPrice();

        if ($selling <= 0.0) {
            return 0.0;
        }

        return round($this->getProfit() / $selling * 100, 2);
    }

    public function isManual(): bool
    {
        return $this->getBool('is_manual');
    }

    public function isActive(): bool
    {
        return $this->getString('status', self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function getCurrency(): string
    {
        return $this->getString('currency');
    }
}
