<?php

/**
 * Disk plan model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A disk tier of the pricing catalogue.
 */
final class DiskPlan extends AbstractPlan
{
    public function resourceType(): string
    {
        return 'disk';
    }

    public function specColumn(): string
    {
        return 'disk_gb';
    }

    public function getDiskGb(): int
    {
        return $this->getSpec();
    }
}
