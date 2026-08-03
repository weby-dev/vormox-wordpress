<?php

/**
 * Disk plan repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\DiskPlan;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for disk tiers.
 */
final class DiskPlanRepository extends AbstractPlanRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::DISK_PLANS;
    }

    protected function modelClass(): string
    {
        return DiskPlan::class;
    }

    public function specColumn(): string
    {
        return 'disk_gb';
    }
}
