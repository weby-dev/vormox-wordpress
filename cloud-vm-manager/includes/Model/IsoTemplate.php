<?php

/**
 * ISO template model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * An operating system image available inside a zone.
 */
final class IsoTemplate extends AbstractModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REMOVED = 'removed';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'provider_id' => 'int',
            'zone_id' => 'int',
            'zone_remote_id' => 'int',
            'remote_id' => 'int',
            'iso_name' => 'string',
            'os_type' => 'string',
            'payload' => 'json',
            'checksum' => 'string',
            'status' => 'string',
            'synced_at' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
        ];
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    public function getZoneId(): int
    {
        return $this->getInt('zone_id');
    }

    public function getZoneRemoteId(): int
    {
        return $this->getInt('zone_remote_id');
    }

    /**
     * Backend ISO identifier used when creating or rebuilding a machine.
     */
    public function getRemoteId(): int
    {
        return $this->getInt('remote_id');
    }

    public function getIsoName(): string
    {
        return $this->getString('iso_name');
    }

    public function getOsType(): string
    {
        return $this->getString('os_type');
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
