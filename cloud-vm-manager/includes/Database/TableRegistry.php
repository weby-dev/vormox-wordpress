<?php

/**
 * Table registry.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Database;

use CloudVmManager\Exception\DatabaseException;
use wpdb;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Single source of truth for the custom table names.
 *
 * Table names are never written literally anywhere else, which keeps prefixes
 * consistent on multisite installations and guarantees that only known
 * identifiers ever reach an SQL statement.
 */
final class TableRegistry
{
    public const PROVIDERS = 'providers';
    public const ZONES = 'zones';
    public const ISO_TEMPLATES = 'iso_templates';
    public const CPU_PLANS = 'cpu_plans';
    public const RAM_PLANS = 'ram_plans';
    public const DISK_PLANS = 'disk_plans';
    public const BANDWIDTH_PLANS = 'bandwidth_plans';
    public const PRICING = 'pricing';
    public const VM_ORDERS = 'vm_orders';
    public const VM_LOGS = 'vm_logs';
    public const API_CACHE = 'api_cache';
    public const SYNC_RUNS = 'sync_runs';
    public const CUSTOMER_ACCOUNTS = 'customer_accounts';

    /**
     * Prefix applied to every plugin table, after the WordPress table prefix.
     */
    private const NAMESPACE_PREFIX = 'cvm_';

    /**
     * @var wpdb
     */
    private $wpdb;

    public function __construct(wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    /**
     * Every table key owned by the plugin.
     *
     * @return string[]
     */
    public function keys(): array
    {
        return [
            self::PROVIDERS,
            self::ZONES,
            self::ISO_TEMPLATES,
            self::CPU_PLANS,
            self::RAM_PLANS,
            self::DISK_PLANS,
            self::BANDWIDTH_PLANS,
            self::PRICING,
            self::VM_ORDERS,
            self::VM_LOGS,
            self::API_CACHE,
            self::SYNC_RUNS,
            self::CUSTOMER_ACCOUNTS,
        ];
    }

    /**
     * Resolve a table key to its fully prefixed name.
     *
     * @throws DatabaseException When the key is unknown.
     */
    public function name(string $key): string
    {
        if (!in_array($key, $this->keys(), true)) {
            throw new DatabaseException(sprintf('Unknown plugin table "%s".', $key));
        }

        return $this->wpdb->prefix . self::NAMESPACE_PREFIX . $key;
    }

    /**
     * Fully prefixed names of every plugin table.
     *
     * @return string[] Keyed by table key.
     */
    public function all(): array
    {
        $tables = [];

        foreach ($this->keys() as $key) {
            $tables[$key] = $this->name($key);
        }

        return $tables;
    }

    /**
     * Whether the table currently exists in the database.
     */
    public function exists(string $key): bool
    {
        $table = $this->name($key);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $found = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))
        );

        return $found === $table;
    }

    /**
     * Charset and collation clause used by every plugin table.
     */
    public function charsetCollate(): string
    {
        return $this->wpdb->get_charset_collate();
    }
}
