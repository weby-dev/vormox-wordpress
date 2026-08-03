<?php

/**
 * Bandwidth plan repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\BandwidthPlan;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for bandwidth tiers.
 */
final class BandwidthPlanRepository extends AbstractPlanRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::BANDWIDTH_PLANS;
    }

    protected function modelClass(): string
    {
        return BandwidthPlan::class;
    }

    protected function specColumn(): string
    {
        return 'bandwidth_gb';
    }
}
