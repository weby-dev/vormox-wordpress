<?php

/**
 * Schema migrator.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Database;

defined('ABSPATH') || exit;

/**
 * Keeps the installed schema in sync with the shipped schema version.
 *
 * `dbDelta()` only runs when the stored version differs from the shipped one, or
 * when a table went missing, which keeps normal requests free of schema queries.
 */
final class Migrator
{
    private const VERSION_OPTION = 'cvm_db_version';

    /**
     * Transient guarding against concurrent upgrades.
     */
    private const LOCK_KEY = 'cvm_db_upgrade_lock';

    private const LOCK_TTL = 60;

    /**
     * @var Installer
     */
    private $installer;

    public function __construct(Installer $installer)
    {
        $this->installer = $installer;
    }

    /**
     * Installed schema version, empty when the plugin was never installed.
     */
    public function installedVersion(): string
    {
        $version = get_option(self::VERSION_OPTION, '');

        return is_string($version) ? $version : '';
    }

    public function targetVersion(): string
    {
        return CVM_DB_VERSION;
    }

    /**
     * Whether the database schema is behind the shipped schema.
     */
    public function needsUpgrade(): bool
    {
        return version_compare($this->installedVersion(), $this->targetVersion(), '<');
    }

    /**
     * Run the installer when the schema is outdated or incomplete.
     *
     * @return bool Whether an upgrade was performed.
     */
    public function maybeUpgrade(): bool
    {
        if (!$this->needsUpgrade() && $this->installedVersion() !== '') {
            return false;
        }

        if (get_transient(self::LOCK_KEY) !== false) {
            return false;
        }

        set_transient(self::LOCK_KEY, 1, self::LOCK_TTL);

        try {
            $from = $this->installedVersion();
            $this->installer->install();

            /**
             * Fires after the database schema has been upgraded.
             *
             * @param string $from Previously installed schema version.
             * @param string $to   Schema version that is now installed.
             */
            do_action('cloud_vm_manager_schema_upgraded', $from, $this->targetVersion());
        } finally {
            delete_transient(self::LOCK_KEY);
        }

        return true;
    }

    /**
     * Force the installer to run, regardless of the stored version.
     *
     * @return string[] dbDelta messages.
     */
    public function forceUpgrade(): array
    {
        return $this->installer->install();
    }
}
