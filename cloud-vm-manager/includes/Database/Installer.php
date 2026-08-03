<?php

/**
 * Table installer.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Database;

use wpdb;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Creates, verifies and drops the custom tables.
 */
final class Installer
{
    /**
     * @var wpdb
     */
    private $wpdb;

    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var TableRegistry
     */
    private $tables;

    public function __construct(wpdb $wpdb, Schema $schema, TableRegistry $tables)
    {
        $this->wpdb = $wpdb;
        $this->schema = $schema;
        $this->tables = $tables;
    }

    /**
     * Create or update every plugin table.
     *
     * @return string[] Messages returned by dbDelta, keyed by column or index.
     */
    public function install(): array
    {
        if (!function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $previousSuppress = $this->wpdb->suppress_errors(true);
        $results = [];

        foreach ($this->schema->statements() as $statement) {
            $results[] = dbDelta($statement);
        }

        $this->wpdb->suppress_errors($previousSuppress);

        // Autoloaded: the migrator reads this option on every request.
        update_option('cvm_db_version', CVM_DB_VERSION, 'yes');

        $flattened = [];

        foreach ($results as $result) {
            if (is_array($result)) {
                $flattened = array_merge($flattened, array_values($result));
            }
        }

        return $flattened;
    }

    /**
     * Table keys that are declared but currently missing in the database.
     *
     * @return string[]
     */
    public function missingTables(): array
    {
        $missing = [];

        foreach ($this->tables->keys() as $key) {
            if (!$this->tables->exists($key)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Whether every plugin table exists.
     */
    public function isInstalled(): bool
    {
        return $this->missingTables() === [];
    }

    /**
     * Drop every plugin table. Only used by the uninstall routine.
     */
    public function dropTables(): void
    {
        foreach ($this->tables->all() as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $this->wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }
    }
}
