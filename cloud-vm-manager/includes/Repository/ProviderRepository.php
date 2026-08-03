<?php

/**
 * Provider repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\Provider;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for cloud providers.
 */
final class ProviderRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::PROVIDERS;
    }

    protected function modelClass(): string
    {
        return Provider::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'name' => '%s',
            'slug' => '%s',
            'host_url' => '%s',
            'api_url' => '%s',
            'email' => '%s',
            'password' => '%s',
            'api_token' => '%s',
            'token_expires_at' => '%s',
            'verify_ssl' => '%d',
            'timeout' => '%d',
            'region' => '%s',
            'description' => '%s',
            'status' => '%s',
            'platform_version' => '%s',
            'currency' => '%s',
            'is_active' => '%d',
            'is_default' => '%d',
            'sort_order' => '%d',
            'last_error' => '%s',
            'last_connected_at' => '%s',
            'last_synced_at' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    public function findProvider(int $id): ?Provider
    {
        $provider = $this->find($id);

        return $provider instanceof Provider ? $provider : null;
    }

    public function findBySlug(string $slug): ?Provider
    {
        $provider = $this->findOneBy(['slug' => $slug]);

        return $provider instanceof Provider ? $provider : null;
    }

    /**
     * Providers ordered for display.
     *
     * @return Provider[]
     */
    public function all(bool $activeOnly = false): array
    {
        $conditions = $activeOnly ? ['is_active' => 1] : [];

        /** @var Provider[] $providers */
        $providers = $this->findBy($conditions, ['order_by' => 'sort_order', 'order' => 'ASC']);

        return $providers;
    }

    /**
     * Provider used when a product does not name one explicitly.
     */
    public function getDefault(): ?Provider
    {
        $provider = $this->findOneBy(['is_default' => 1, 'is_active' => 1]);

        if ($provider instanceof Provider) {
            return $provider;
        }

        $providers = $this->findBy(['is_active' => 1], ['order_by' => 'sort_order', 'order' => 'ASC', 'limit' => 1]);

        return $providers === [] ? null : $providers[0];
    }

    /**
     * Mark a single provider as the default one.
     */
    public function markAsDefault(int $providerId): void
    {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $this->wpdb->query('UPDATE `' . $this->table() . '` SET is_default = 0 WHERE is_default = 1');

        $this->update($providerId, ['is_default' => 1]);
    }

    /**
     * Build a slug that is unique inside the table.
     */
    public function uniqueSlug(string $base, int $ignoreId = 0): string
    {
        $slug = sanitize_title($base);

        if ($slug === '') {
            $slug = 'provider';
        }

        $candidate = $slug;
        $suffix = 2;

        while ($this->slugExists($candidate, $ignoreId)) {
            $candidate = $slug . '-' . $suffix;
            ++$suffix;
        }

        return $candidate;
    }

    /**
     * Persist the outcome of a connection attempt.
     */
    public function markConnectionResult(
        int $providerId,
        bool $success,
        string $platformVersion = '',
        string $error = ''
    ): void {
        $data = [
            'status' => $success ? Provider::STATUS_CONNECTED : Provider::STATUS_ERROR,
            'last_error' => $success ? '' : $error,
        ];

        if ($success) {
            $data['last_connected_at'] = $this->now();
        }

        if ($platformVersion !== '') {
            $data['platform_version'] = $platformVersion;
        }

        $this->update($providerId, $data);
    }

    /**
     * Store a freshly issued API token.
     *
     * @param string $encryptedToken Ciphertext produced by the encryptor.
     * @param string $expiresAt      UTC expiry in MySQL format, empty when unknown.
     */
    public function storeToken(int $providerId, string $encryptedToken, string $expiresAt = ''): void
    {
        $this->update(
            $providerId,
            [
                'api_token' => $encryptedToken,
                'token_expires_at' => $expiresAt !== '' ? $expiresAt : null,
                'status' => Provider::STATUS_CONNECTED,
                'last_connected_at' => $this->now(),
                'last_error' => '',
            ]
        );
    }

    /**
     * Drop the stored token and mark the provider as disconnected.
     */
    public function clearToken(int $providerId): void
    {
        $this->update(
            $providerId,
            [
                'api_token' => '',
                'token_expires_at' => null,
                'status' => Provider::STATUS_DISCONNECTED,
            ]
        );
    }

    private function slugExists(string $slug, int $ignoreId): bool
    {
        $conditions = ['slug' => $slug];

        if ($ignoreId > 0) {
            $conditions['id'] = ['operator' => '!=', 'value' => $ignoreId];
        }

        return $this->exists($conditions);
    }
}
