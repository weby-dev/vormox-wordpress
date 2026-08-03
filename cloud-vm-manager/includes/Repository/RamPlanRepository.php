<?php

/**
 * RAM plan repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\RamPlan;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for memory tiers.
 */
final class RamPlanRepository extends AbstractPlanRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::RAM_PLANS;
    }

    protected function modelClass(): string
    {
        return RamPlan::class;
    }

    public function specColumn(): string
    {
        return 'ram_mb';
    }
}
