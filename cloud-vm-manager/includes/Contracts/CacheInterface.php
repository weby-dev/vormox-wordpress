<?php

/**
 * Cache contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistent cache used for API responses and catalogue lookups.
 */
interface CacheInterface
{
    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param mixed $value
     * @param int   $ttl   Lifetime in seconds. Zero falls back to the configured default.
     */
    public function set(string $key, $value, int $ttl = 0): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    /**
     * Remove every cached entry, optionally limited to a provider.
     */
    public function flush(int $providerId = 0): int;

    /**
     * Return the cached value or compute, store and return it.
     *
     * @param callable $callback Producer invoked on a cache miss.
     *
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = 0, int $providerId = 0);
}
