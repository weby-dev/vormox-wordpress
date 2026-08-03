<?php

/**
 * Zone model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A geographic zone synchronised from the backend catalogue.
 */
final class Zone extends AbstractModel
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
            'remote_id' => 'int',
            'name' => 'string',
            'country' => 'string',
            'description' => 'string',
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

    /**
     * Backend identifier. Always used for provisioning, never the name.
     */
    public function getRemoteId(): int
    {
        return $this->getInt('remote_id');
    }

    public function getName(): string
    {
        return $this->getString('name');
    }

    public function getCountry(): string
    {
        return $this->getString('country');
    }

    public function isActive(): bool
    {
        return $this->getString('status', self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    /**
     * Raw backend payload.
     *
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->getArray('payload');
    }
}
