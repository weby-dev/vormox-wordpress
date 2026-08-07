<?php

/**
 * Upgrade option.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Service\Billing;

use JsonSerializable;

defined('ABSPATH') || exit;

/**
 * One resource tier a machine can move to.
 */
final class UpgradeOption implements JsonSerializable
{
    /**
     * @var int
     */
    private $priceId;

    /**
     * @var string
     */
    private $label;

    /**
     * @var float
     */
    private $monthlyPrice;

    /**
     * @var float
     */
    private $proRataCost;

    /**
     * @var bool
     */
    private $current;

    public function __construct(
        int $priceId,
        string $label,
        float $monthlyPrice,
        float $proRataCost,
        bool $current = false
    ) {
        $this->priceId = $priceId;
        $this->label = $label;
        $this->monthlyPrice = round($monthlyPrice, 4);
        $this->proRataCost = round($proRataCost, 4);
        $this->current = $current;
    }

    /**
     * Backend pricing identifier submitted with the upgrade.
     */
    public function priceId(): int
    {
        return $this->priceId;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function monthlyPrice(): float
    {
        return $this->monthlyPrice;
    }

    /**
     * What the backend charges to move to this tier for the remaining term.
     */
    public function proRataCost(): float
    {
        return $this->proRataCost;
    }

    public function isCurrent(): bool
    {
        return $this->current;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'price_id' => $this->priceId,
            'label' => $this->label,
            'monthly_price' => $this->monthlyPrice,
            'pro_rata_cost' => $this->proRataCost,
            'current' => $this->current,
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
