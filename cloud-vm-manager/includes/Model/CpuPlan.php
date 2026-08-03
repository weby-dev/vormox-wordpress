<?php

/**
 * CPU plan model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A CPU tier of the pricing catalogue.
 */
final class CpuPlan extends AbstractPlan
{
    public function resourceType(): string
    {
        return 'cpu';
    }

    public function specColumn(): string
    {
        return 'cores';
    }

    public function getCores(): int
    {
        return $this->getSpec();
    }
}
