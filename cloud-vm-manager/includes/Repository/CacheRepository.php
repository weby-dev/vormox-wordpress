<?php

/**
 * API cache repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\CacheItem;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for cached API responses.
 */
final class CacheRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::API_CACHE;
    }

    protected function modelClass(): string
    {
        return CacheItem::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'cache_key' => '%s',
            'provider_id' => '%d',
            'payload' => '%s',
            'expires_at' => '%s',
            'created_at' => '%s',
        ];
    }

    /**
     * The cache table carries its own expiry timestamp.
     */
    protected function hasTimestamps(): bool
    {
        return false;
    }

    /**
     * Serialised payload of a key that has not expired yet.
     */
    public function read(string $key): ?string
    {
        $sql = 'SELECT payload FROM `' . $this->table() . '` WHERE cache_key = %s AND expires_at > %s LIMIT 1';
        $payload = $this->scalar($sql, [$key, $this->now()]);

        return is_string($payload) ? $payload : null;
    }

    /**
     * Write a payload, replacing any previous entry for the key.
     */
    public function write(string $key, string $payload, int $ttl, int $providerId = 0): bool
    {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + max(1, $ttl));

        $existing = $this->findOneBy(['cache_key' => $key]);

        if ($existing !== null) {
            return $this->update(
                $existing->id(),
                [
                    'payload' => $payload,
                    'provider_id' => $providerId,
                    'expires_at' => $expiresAt,
                ]
            );
        }

        return $this->insert(
            [
                'cache_key' => $key,
                'provider_id' => $providerId,
                'payload' => $payload,
                'expires_at' => $expiresAt,
                'created_at' => $this->now(),
            ]
        ) > 0;
    }

    /**
     * Remove a single key.
     */
    public function forget(string $key): bool
    {
        return $this->deleteWhere(['cache_key' => $key]) > 0;
    }

    /**
     * Remove every key, or every key of a single provider.
     *
     * @return int Number of removed entries.
     */
    public function flush(int $providerId = 0): int
    {
        if ($providerId > 0) {
            return $this->deleteWhere(['provider_id' => $providerId]);
        }

        $total = $this->count();
        $this->truncate();

        return $total;
    }

    /**
     * Remove entries whose lifetime elapsed.
     *
     * @return int Number of removed entries.
     */
    public function purgeExpired(): int
    {
        return $this->deleteWhere(
            [
                'expires_at' => ['operator' => '<=', 'value' => $this->now()],
            ]
        );
    }
}
