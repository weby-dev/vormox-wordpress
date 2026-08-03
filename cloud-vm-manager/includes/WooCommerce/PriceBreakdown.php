<?php

/**
 * Price breakdown.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * What one machine costs and what it sells for over a billing cycle.
 */
final class PriceBreakdown implements JsonSerializable
{
    /**
     * @var float
     */
    private $providerCost;

    /**
     * @var float
     */
    private $sellingPrice;

    /**
     * @var int
     */
    private $months;

    /**
     * @var string
     */
    private $currency;

    /**
     * Per resource provider price and selling price, both monthly.
     *
     * @var array<string, array{provider: float, selling: float, label: string}>
     */
    private $lines;

    /**
     * Resources without a matching price book entry.
     *
     * @var string[]
     */
    private $missing;

    /**
     * @param array<string, array{provider: float, selling: float, label: string}> $lines
     * @param string[]                                                            $missing
     */
    public function __construct(
        float $providerCost,
        float $sellingPrice,
        int $months,
        string $currency,
        array $lines = [],
        array $missing = []
    ) {
        $this->providerCost = round($providerCost, 4);
        $this->sellingPrice = round($sellingPrice, 4);
        $this->months = max(1, $months);
        $this->currency = $currency;
        $this->lines = $lines;
        $this->missing = $missing;
    }

    /**
     * Provider cost for the whole billing cycle.
     */
    public function providerCost(): float
    {
        return $this->providerCost;
    }

    /**
     * Price charged to the customer for the whole billing cycle.
     */
    public function sellingPrice(): float
    {
        return $this->sellingPrice;
    }

    public function months(): int
    {
        return $this->months;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Absolute markup over the billing cycle.
     */
    public function markup(): float
    {
        return round($this->sellingPrice - $this->providerCost, 4);
    }

    /**
     * Markup as a percentage of the provider cost.
     */
    public function markupPercent(): float
    {
        if ($this->providerCost <= 0.0) {
            return 0.0;
        }

        return round($this->markup() / $this->providerCost * 100, 2);
    }

    /**
     * Profit as a percentage of the selling price.
     */
    public function marginPercent(): float
    {
        if ($this->sellingPrice <= 0.0) {
            return 0.0;
        }

        return round($this->markup() / $this->sellingPrice * 100, 2);
    }

    /**
     * @return array<string, array{provider: float, selling: float, label: string}>
     */
    public function lines(): array
    {
        return $this->lines;
    }

    /**
     * @return string[]
     */
    public function missing(): array
    {
        return $this->missing;
    }

    /**
     * Whether every resource resolved to a price book entry.
     */
    public function isComplete(): bool
    {
        return $this->missing === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider_cost' => $this->providerCost,
            'selling_price' => $this->sellingPrice,
            'markup' => $this->markup(),
            'markup_percent' => $this->markupPercent(),
            'margin_percent' => $this->marginPercent(),
            'months' => $this->months,
            'currency' => $this->currency,
            'lines' => $this->lines,
            'missing' => $this->missing,
            'complete' => $this->isComplete(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
