<?php

/**
 * Base resource plan repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Model\AbstractPlan;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Shared persistence logic of the four resource plan tables.
 */
abstract class AbstractPlanRepository extends AbstractRepository
{
    /**
     * Resource specific column of the table, for example "cores".
     */
    abstract public function specColumn(): string;

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return array_merge(
            [
                'id' => '%d',
                'provider_id' => '%d',
                'remote_id' => '%d',
                'plan_type' => '%s',
                'label' => '%s',
                'price' => '%f',
                'currency' => '%s',
                'payload' => '%s',
                'checksum' => '%s',
                'status' => '%s',
                'synced_at' => '%s',
                'created_at' => '%s',
                'updated_at' => '%s',
            ],
            [$this->specColumn() => '%d']
        );
    }

    /**
     * Tiers of a provider, optionally limited to a plan type.
     *
     * @return AbstractPlan[]
     */
    public function forProvider(int $providerId, string $planType = '', bool $activeOnly = true): array
    {
        $conditions = ['provider_id' => $providerId];

        if ($planType !== '') {
            $conditions['plan_type'] = $planType;
        }

        if ($activeOnly) {
            $conditions['status'] = AbstractPlan::STATUS_ACTIVE;
        }

        /** @var AbstractPlan[] $plans */
        $plans = $this->findBy($conditions, ['order_by' => $this->specColumn(), 'order' => 'ASC']);

        return $plans;
    }

    /**
     * Look a tier up by its backend pricing identifier.
     */
    public function findByRemoteId(int $providerId, string $planType, int $remoteId): ?AbstractPlan
    {
        $plan = $this->findOneBy(
            [
                'provider_id' => $providerId,
                'plan_type' => $planType,
                'remote_id' => $remoteId,
            ]
        );

        return $plan instanceof AbstractPlan ? $plan : null;
    }

    /**
     * Flag tiers that disappeared from the backend catalogue.
     *
     * @param int[] $keepRemoteIds
     *
     * @return int Number of tiers marked as removed.
     */
    public function markMissingAsRemoved(int $providerId, string $planType, array $keepRemoteIds): int
    {
        $conditions = [
            'provider_id' => $providerId,
            'plan_type' => $planType,
            'status' => AbstractPlan::STATUS_ACTIVE,
        ];

        if ($keepRemoteIds !== []) {
            $conditions['remote_id'] = ['operator' => 'NOT IN', 'value' => $keepRemoteIds];
        }

        $stale = $this->findBy($conditions);

        foreach ($stale as $plan) {
            $this->update($plan->id(), ['status' => AbstractPlan::STATUS_REMOVED]);
        }

        return count($stale);
    }
}
