<?php

/**
 * CPU plan repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\CpuPlan;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for CPU tiers.
 */
final class CpuPlanRepository extends AbstractPlanRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::CPU_PLANS;
    }

    protected function modelClass(): string
    {
        return CpuPlan::class;
    }

    protected function specColumn(): string
    {
        return 'cores';
    }
}
