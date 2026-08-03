<?php

/**
 * Cache item model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A cached API response.
 */
final class CacheItem extends AbstractModel
{
    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'cache_key' => 'string',
            'provider_id' => 'int',
            'payload' => 'string',
            'expires_at' => 'string',
            'created_at' => 'string',
        ];
    }

    public function getKey(): string
    {
        return $this->getString('cache_key');
    }

    public function getPayload(): string
    {
        return $this->getString('payload');
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    /**
     * Whether the entry outlived its time to live.
     */
    public function isExpired(): bool
    {
        $expiry = $this->getDateTime('expires_at');

        return $expiry === null || $expiry->getTimestamp() <= time();
    }
}
