<?php

/**
 * Bandwidth plan model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A bandwidth tier of the pricing catalogue.
 */
final class BandwidthPlan extends AbstractPlan
{
    public function resourceType(): string
    {
        return 'bandwidth';
    }

    public function specColumn(): string
    {
        return 'bandwidth_gb';
    }

    public function getBandwidthGb(): int
    {
        return $this->getSpec();
    }
}
