<?php

/**
 * Product price calculation.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\WooCommerce;

use CloudVmManager\Model\AbstractPlan;
use CloudVmManager\Model\PricingRule;
use CloudVmManager\Model\VmOrder;
use CloudVmManager\Repository\PricingRepository;

defined('ABSPATH') || exit;

/**
 * Turns a machine configuration into a price.
 *
 * The provider cost and the selling price both come from the local price book,
 * which the synchronisation engine keeps current. Monthly figures are
 * multiplied by the number of months in the billing cycle.
 */
final class PriceCalculator
{
    /**
     * @var PricingRepository
     */
    private $pricing;

    public function __construct(PricingRepository $pricing)
    {
        $this->pricing = $pricing;
    }

    /**
     * Price one machine configuration.
     *
     * @param array<string, int> $priceIds Backend pricing identifier per resource.
     */
    public function calculate(
        int $providerId,
        string $planType,
        array $priceIds,
        string $billingCycle,
        string $currency = ''
    ): PriceBreakdown {
        $months = VmOrder::monthsForCycle($billingCycle);
        $planType = strtoupper($planType) === AbstractPlan::TYPE_DEDICATED
            ? AbstractPlan::TYPE_DEDICATED
            : AbstractPlan::TYPE_SHARED;

        $providerMonthly = 0.0;
        $sellingMonthly = 0.0;
        $lines = [];
        $missing = [];

        foreach (PricingRule::resourceTypes() as $resource) {
            $remoteId = (int) ($priceIds[$resource] ?? 0);

            if ($remoteId <= 0) {
                $missing[] = $resource;

                continue;
            }

            $rule = $this->pricing->findByRemotePriceId($providerId, $resource, $planType, $remoteId);

            if (!$rule instanceof PricingRule) {
                $missing[] = $resource;

                continue;
            }

            $providerMonthly += $rule->getProviderPrice();
            $sellingMonthly += $rule->getSellingPrice();

            $lines[$resource] = [
                'provider' => $rule->getProviderPrice(),
                'selling' => $rule->getSellingPrice(),
                'label' => $rule->getLabel(),
            ];

            if ($currency === '' && $rule->getCurrency() !== '') {
                $currency = $rule->getCurrency();
            }
        }

        return new PriceBreakdown(
            $providerMonthly * $months,
            $sellingMonthly * $months,
            $months,
            $currency,
            $lines,
            $missing
        );
    }

    /**
     * Price the configuration stored on a product.
     */
    public function calculateForProduct(ProductConfiguration $configuration): PriceBreakdown
    {
        return $this->calculate(
            $configuration->providerId(),
            $configuration->planType(),
            $configuration->priceIds(),
            $configuration->billingCycle(),
            $configuration->currency()
        );
    }
}
