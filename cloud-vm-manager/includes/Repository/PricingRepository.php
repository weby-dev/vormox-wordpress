<?php

/**
 * Pricing repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\PricingRule;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for the local price book.
 */
final class PricingRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::PRICING;
    }

    protected function modelClass(): string
    {
        return PricingRule::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'provider_id' => '%d',
            'resource_type' => '%s',
            'plan_type' => '%s',
            'remote_price_id' => '%d',
            'plan_id' => '%d',
            'label' => '%s',
            'provider_price' => '%f',
            'markup_type' => '%s',
            'markup_value' => '%f',
            'selling_price' => '%f',
            'currency' => '%s',
            'is_manual' => '%d',
            'status' => '%s',
            'synced_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    /**
     * Price book entries of a resource.
     *
     * @return PricingRule[]
     */
    public function forResource(
        int $providerId,
        string $resourceType,
        string $planType = '',
        bool $activeOnly = true
    ): array {
        $conditions = [
            'provider_id' => $providerId,
            'resource_type' => $resourceType,
        ];

        if ($planType !== '') {
            $conditions['plan_type'] = $planType;
        }

        if ($activeOnly) {
            $conditions['status'] = PricingRule::STATUS_ACTIVE;
        }

        /** @var PricingRule[] $rules */
        $rules = $this->findBy($conditions, ['order_by' => 'provider_price', 'order' => 'ASC']);

        return $rules;
    }

    /**
     * Look an entry up by the backend pricing identifier.
     */
    public function findByRemotePriceId(
        int $providerId,
        string $resourceType,
        string $planType,
        int $remotePriceId
    ): ?PricingRule {
        $rule = $this->findOneBy(
            [
                'provider_id' => $providerId,
                'resource_type' => $resourceType,
                'plan_type' => $planType,
                'remote_price_id' => $remotePriceId,
            ]
        );

        return $rule instanceof PricingRule ? $rule : null;
    }

    /**
     * Apply a markup to every non manual entry of a provider.
     *
     * @return int Number of updated entries.
     */
    public function applyMarkup(int $providerId, string $markupType, float $markupValue, string $resourceType = ''): int
    {
        $conditions = [
            'provider_id' => $providerId,
            'is_manual' => 0,
        ];

        if ($resourceType !== '') {
            $conditions['resource_type'] = $resourceType;
        }

        /** @var PricingRule[] $rules */
        $rules = $this->findBy($conditions);

        foreach ($rules as $rule) {
            $rule->set('markup_type', $markupType);
            $rule->set('markup_value', $markupValue);

            $this->update(
                $rule->id(),
                [
                    'markup_type' => $markupType,
                    'markup_value' => $markupValue,
                    'selling_price' => $rule->calculateSellingPrice(),
                ]
            );
        }

        return count($rules);
    }

    /**
     * Flag entries that disappeared from the backend catalogue.
     *
     * @param int[] $keepRemoteIds
     *
     * @return int Number of entries marked as removed.
     */
    public function markMissingAsRemoved(
        int $providerId,
        string $resourceType,
        string $planType,
        array $keepRemoteIds
    ): int {
        $conditions = [
            'provider_id' => $providerId,
            'resource_type' => $resourceType,
            'plan_type' => $planType,
            'status' => PricingRule::STATUS_ACTIVE,
        ];

        if ($keepRemoteIds !== []) {
            $conditions['remote_price_id'] = ['operator' => 'NOT IN', 'value' => $keepRemoteIds];
        }

        $stale = $this->findBy($conditions);

        foreach ($stale as $rule) {
            $this->update($rule->id(), ['status' => PricingRule::STATUS_REMOVED]);
        }

        return count($stale);
    }
}
