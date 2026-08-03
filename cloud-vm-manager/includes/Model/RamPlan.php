<?php

/**
 * RAM plan model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A memory tier of the pricing catalogue.
 */
final class RamPlan extends AbstractPlan
{
    public function resourceType(): string
    {
        return 'ram';
    }

    public function specColumn(): string
    {
        return 'ram_mb';
    }

    public function getRamMb(): int
    {
        return $this->getSpec();
    }

    public function getRamGb(): float
    {
        return round($this->getSpec() / 1024, 2);
    }
}
