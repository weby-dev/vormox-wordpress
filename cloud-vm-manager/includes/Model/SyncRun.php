<?php

/**
 * Sync run model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Result of a single catalogue synchronisation run.
 */
final class SyncRun extends AbstractModel
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';

    public const RESOURCE_ZONES = 'zones';
    public const RESOURCE_ISOS = 'isos';
    public const RESOURCE_CPU = 'cpu';
    public const RESOURCE_RAM = 'ram';
    public const RESOURCE_DISK = 'disk';
    public const RESOURCE_BANDWIDTH = 'bandwidth';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'provider_id' => 'int',
            'resource' => 'string',
            'status' => 'string',
            'added' => 'int',
            'updated' => 'int',
            'removed' => 'int',
            'unchanged' => 'int',
            'message' => 'string',
            'context' => 'json',
            'duration_ms' => 'int',
            'started_at' => 'string',
            'finished_at' => 'string',
        ];
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    public function getResource(): string
    {
        return $this->getString('resource');
    }

    public function getStatus(): string
    {
        return $this->getString('status', self::STATUS_RUNNING);
    }

    public function isSuccessful(): bool
    {
        return $this->getStatus() === self::STATUS_SUCCESS;
    }

    public function getAdded(): int
    {
        return $this->getInt('added');
    }

    public function getUpdated(): int
    {
        return $this->getInt('updated');
    }

    public function getRemoved(): int
    {
        return $this->getInt('removed');
    }

    public function getUnchanged(): int
    {
        return $this->getInt('unchanged');
    }

    public function getMessage(): string
    {
        return $this->getString('message');
    }

    public function getDurationMs(): int
    {
        return $this->getInt('duration_ms');
    }

    /**
     * Total number of records touched by the run.
     */
    public function getTotalChanges(): int
    {
        return $this->getAdded() + $this->getUpdated() + $this->getRemoved();
    }
}
