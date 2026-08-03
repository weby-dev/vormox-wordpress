<?php

/**
 * Base resource plan model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Shared behaviour of the CPU, RAM, disk and bandwidth catalogue tiers.
 */
abstract class AbstractPlan extends AbstractModel
{
    public const TYPE_SHARED = 'SHARED';
    public const TYPE_DEDICATED = 'DEDICATED';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';

    /**
     * Resource slug: cpu, ram, disk or bandwidth.
     */
    abstract public function resourceType(): string;

    /**
     * Column holding the resource specification of the tier.
     */
    abstract public function specColumn(): string;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return array_merge(
            [
                'id' => 'int',
                'provider_id' => 'int',
                'remote_id' => 'int',
                'plan_type' => 'string',
                'label' => 'string',
                'price' => 'float',
                'currency' => 'string',
                'payload' => 'json',
                'checksum' => 'string',
                'status' => 'string',
                'synced_at' => 'string',
                'created_at' => 'string',
                'updated_at' => 'string',
            ],
            [$this->specColumn() => 'int']
        );
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    /**
     * Backend pricing identifier submitted when a machine is created.
     */
    public function getRemoteId(): int
    {
        return $this->getInt('remote_id');
    }

    public function getPlanType(): string
    {
        return $this->getString('plan_type', self::TYPE_SHARED);
    }

    public function isDedicated(): bool
    {
        return $this->getPlanType() === self::TYPE_DEDICATED;
    }

    public function getLabel(): string
    {
        return $this->getString('label');
    }

    /**
     * Monthly provider price of the tier.
     */
    public function getPrice(): float
    {
        return $this->getFloat('price');
    }

    public function getCurrency(): string
    {
        return $this->getString('currency');
    }

    /**
     * Numeric specification of the tier, for example cores or megabytes.
     */
    public function getSpec(): int
    {
        return $this->getInt($this->specColumn());
    }

    public function isActive(): bool
    {
        return $this->getString('status', self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->getArray('payload');
    }
}
