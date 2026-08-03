<?php

/**
 * API response cache.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Support;

use CloudVmManager\Contracts\CacheInterface;
use CloudVmManager\Repository\CacheRepository;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Two tier cache: a per request memory map in front of the database table.
 *
 * Keys are namespaced and hashed so that any key length fits the indexed column
 * and no user supplied value ever reaches a statement unescaped.
 */
final class Cache implements CacheInterface
{
    private const KEY_PREFIX = 'cvm_';

    /**
     * @var CacheRepository
     */
    private $repository;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var array<string, mixed>
     */
    private $memory = [];

    public function __construct(CacheRepository $repository, Settings $settings)
    {
        $this->repository = $repository;
        $this->settings = $settings;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (!$this->isEnabled()) {
            return $default;
        }

        $hashed = $this->hashKey($key);

        if (array_key_exists($hashed, $this->memory)) {
            return $this->memory[$hashed];
        }

        $payload = $this->repository->read($hashed);

        if ($payload === null) {
            return $default;
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded) || !array_key_exists('value', $decoded)) {
            return $default;
        }

        $this->memory[$hashed] = $decoded['value'];

        return $decoded['value'];
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value, int $ttl = 0): bool
    {
        return $this->put($key, $value, $ttl, 0);
    }

    /**
     * Store a value together with the provider it belongs to.
     *
     * @param mixed $value
     */
    public function put(string $key, $value, int $ttl = 0, int $providerId = 0): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $hashed = $this->hashKey($key);
        $payload = wp_json_encode(['value' => $value]);

        if (!is_string($payload)) {
            return false;
        }

        $this->memory[$hashed] = $value;

        return $this->repository->write($hashed, $payload, $this->resolveTtl($ttl), $providerId);
    }

    public function has(string $key): bool
    {
        return $this->get($key, null) !== null;
    }

    public function delete(string $key): bool
    {
        $hashed = $this->hashKey($key);

        unset($this->memory[$hashed]);

        return $this->repository->forget($hashed);
    }

    public function flush(int $providerId = 0): int
    {
        $this->memory = [];

        return $this->repository->flush($providerId);
    }

    /**
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = 0, int $providerId = 0)
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();

        if ($value !== null) {
            $this->put($key, $value, $ttl, $providerId);
        }

        return $value;
    }

    /**
     * Remove entries whose lifetime elapsed.
     */
    public function purgeExpired(): int
    {
        return $this->repository->purgeExpired();
    }

    private function isEnabled(): bool
    {
        return $this->settings->getBool('cache_enabled', true);
    }

    private function resolveTtl(int $ttl): int
    {
        if ($ttl > 0) {
            return $ttl;
        }

        $configured = $this->settings->getInt('cache_ttl', 300);

        return $configured > 0 ? $configured : 300;
    }

    private function hashKey(string $key): string
    {
        return self::KEY_PREFIX . md5($key);
    }
}
