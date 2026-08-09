<?php

/**
 * Database schema definition.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Database;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Declarative schema of every custom table.
 *
 * The statements are written for `dbDelta()`, which imposes a strict format:
 * one column per line, two spaces after `PRIMARY KEY`, `KEY` instead of `INDEX`
 * and no `DEFAULT NULL` clauses (they make dbDelta re-run the same ALTER on
 * every request). Nullable columns therefore rely on the implicit NULL default.
 *
 * WordPress core tables are never touched.
 */
final class Schema
{
    /**
     * @var TableRegistry
     */
    private $tables;

    public function __construct(TableRegistry $tables)
    {
        $this->tables = $tables;
    }

    /**
     * Every `CREATE TABLE` statement keyed by table key.
     *
     * @return string[]
     */
    public function statements(): array
    {
        return [
            TableRegistry::PROVIDERS => $this->providers(),
            TableRegistry::ZONES => $this->zones(),
            TableRegistry::ISO_TEMPLATES => $this->isoTemplates(),
            TableRegistry::CPU_PLANS => $this->cpuPlans(),
            TableRegistry::RAM_PLANS => $this->ramPlans(),
            TableRegistry::DISK_PLANS => $this->diskPlans(),
            TableRegistry::BANDWIDTH_PLANS => $this->bandwidthPlans(),
            TableRegistry::PRICING => $this->pricing(),
            TableRegistry::VM_ORDERS => $this->vmOrders(),
            TableRegistry::VM_LOGS => $this->vmLogs(),
            TableRegistry::API_CACHE => $this->apiCache(),
            TableRegistry::SYNC_RUNS => $this->syncRuns(),
            TableRegistry::CUSTOMER_ACCOUNTS => $this->customerAccounts(),
        ];
    }

    /**
     * Cloud providers and their encrypted credentials.
     */
    private function providers(): string
    {
        $table = $this->tables->name(TableRegistry::PROVIDERS);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(191) NOT NULL DEFAULT '',
            slug varchar(191) NOT NULL DEFAULT '',
            host_url varchar(255) NOT NULL DEFAULT '',
            api_url varchar(255) NOT NULL DEFAULT '',
            email varchar(191) NOT NULL DEFAULT '',
            password text NULL,
            api_token longtext NULL,
            token_expires_at datetime NULL,
            verify_ssl tinyint(1) NOT NULL DEFAULT 1,
            timeout smallint(5) unsigned NOT NULL DEFAULT 30,
            region varchar(100) NOT NULL DEFAULT '',
            description text NULL,
            status varchar(20) NOT NULL DEFAULT 'disconnected',
            platform_version varchar(50) NOT NULL DEFAULT '',
            currency varchar(10) NOT NULL DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            sort_order int(11) NOT NULL DEFAULT 0,
            last_error text NULL,
            last_connected_at datetime NULL,
            last_synced_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Geographic zones synchronised from the backend.
     */
    private function zones(): string
    {
        $table = $this->tables->name(TableRegistry::ZONES);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            name varchar(191) NOT NULL DEFAULT '',
            country varchar(100) NOT NULL DEFAULT '',
            description text NULL,
            payload longtext NULL,
            checksum varchar(32) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            synced_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_remote (provider_id,remote_id),
            KEY status (status)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Operating system images available inside a zone.
     */
    private function isoTemplates(): string
    {
        $table = $this->tables->name(TableRegistry::ISO_TEMPLATES);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            zone_id bigint(20) unsigned NOT NULL DEFAULT 0,
            zone_remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            iso_name varchar(191) NOT NULL DEFAULT '',
            os_type varchar(50) NOT NULL DEFAULT '',
            payload longtext NULL,
            checksum varchar(32) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            synced_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_zone_remote (provider_id,zone_remote_id,remote_id),
            KEY zone_id (zone_id),
            KEY os_type (os_type),
            KEY status (status)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * CPU tiers of the pricing catalogue.
     */
    private function cpuPlans(): string
    {
        return $this->planTable(
            $this->tables->name(TableRegistry::CPU_PLANS),
            'cores int(10) unsigned NOT NULL DEFAULT 0,'
        );
    }

    /**
     * Memory tiers of the pricing catalogue.
     */
    private function ramPlans(): string
    {
        return $this->planTable(
            $this->tables->name(TableRegistry::RAM_PLANS),
            'ram_mb int(10) unsigned NOT NULL DEFAULT 0,'
        );
    }

    /**
     * Disk tiers of the pricing catalogue.
     */
    private function diskPlans(): string
    {
        return $this->planTable(
            $this->tables->name(TableRegistry::DISK_PLANS),
            'disk_gb int(10) unsigned NOT NULL DEFAULT 0,'
        );
    }

    /**
     * Bandwidth tiers of the pricing catalogue.
     */
    private function bandwidthPlans(): string
    {
        return $this->planTable(
            $this->tables->name(TableRegistry::BANDWIDTH_PLANS),
            'bandwidth_gb int(10) unsigned NOT NULL DEFAULT 0,'
        );
    }

    /**
     * Shared column layout of the four resource plan tables.
     *
     * @param string $specColumn Resource specific column definition including the trailing comma.
     */
    private function planTable(string $table, string $specColumn): string
    {
        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            plan_type varchar(20) NOT NULL DEFAULT 'SHARED',
            label varchar(191) NOT NULL DEFAULT '',
            {$specColumn}
            price decimal(18,4) NOT NULL DEFAULT 0.0000,
            currency varchar(10) NOT NULL DEFAULT '',
            payload longtext NULL,
            checksum varchar(32) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'active',
            synced_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_type_remote (provider_id,plan_type,remote_id),
            KEY plan_type (plan_type),
            KEY status (status)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Local price book mapping backend pricing identifiers to selling prices.
     */
    private function pricing(): string
    {
        $table = $this->tables->name(TableRegistry::PRICING);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            resource_type varchar(20) NOT NULL DEFAULT '',
            plan_type varchar(20) NOT NULL DEFAULT 'SHARED',
            remote_price_id bigint(20) unsigned NOT NULL DEFAULT 0,
            plan_id bigint(20) unsigned NOT NULL DEFAULT 0,
            label varchar(191) NOT NULL DEFAULT '',
            provider_price decimal(18,4) NOT NULL DEFAULT 0.0000,
            markup_type varchar(20) NOT NULL DEFAULT 'percent',
            markup_value decimal(18,4) NOT NULL DEFAULT 0.0000,
            selling_price decimal(18,4) NOT NULL DEFAULT 0.0000,
            currency varchar(10) NOT NULL DEFAULT '',
            is_manual tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            synced_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_resource_remote (provider_id,resource_type,plan_type,remote_price_id),
            KEY resource_type (resource_type),
            KEY plan_id (plan_id),
            KEY status (status)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Virtual machines sold through WooCommerce and their provisioning state.
     */
    private function vmOrders(): string
    {
        $table = $this->tables->name(TableRegistry::VM_ORDERS);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            wc_order_item_id bigint(20) unsigned NOT NULL DEFAULT 0,
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_vm_id bigint(20) unsigned NOT NULL DEFAULT 0,
            proxmox_vmid bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_order_id varchar(64) NOT NULL DEFAULT '',
            remote_payment_id varchar(64) NOT NULL DEFAULT '',
            group_id varchar(64) NOT NULL DEFAULT '',
            zone_remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            iso_remote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            plan_type varchar(20) NOT NULL DEFAULT 'SHARED',
            cpu_price_id bigint(20) unsigned NOT NULL DEFAULT 0,
            ram_price_id bigint(20) unsigned NOT NULL DEFAULT 0,
            disk_price_id bigint(20) unsigned NOT NULL DEFAULT 0,
            bandwidth_price_id bigint(20) unsigned NOT NULL DEFAULT 0,
            months smallint(5) unsigned NOT NULL DEFAULT 1,
            quantity smallint(5) unsigned NOT NULL DEFAULT 1,
            billing_cycle varchar(20) NOT NULL DEFAULT 'monthly',
            hostname varchar(191) NOT NULL DEFAULT '',
            ip_address varchar(45) NOT NULL DEFAULT '',
            os_name varchar(191) NOT NULL DEFAULT '',
            cpu_cores int(10) unsigned NOT NULL DEFAULT 0,
            ram_mb int(10) unsigned NOT NULL DEFAULT 0,
            disk_gb int(10) unsigned NOT NULL DEFAULT 0,
            bandwidth_gb int(10) unsigned NOT NULL DEFAULT 0,
            disk_used_mb bigint(20) unsigned NOT NULL DEFAULT 0,
            bandwidth_used_mb bigint(20) unsigned NOT NULL DEFAULT 0,
            usage_updated_at datetime NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            provisioning_status varchar(32) NOT NULL DEFAULT 'pending',
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            building_since datetime NULL,
            last_attempt_at datetime NULL,
            next_retry_at datetime NULL,
            currency varchar(10) NOT NULL DEFAULT '',
            provider_amount decimal(18,4) NOT NULL DEFAULT 0.0000,
            sale_amount decimal(18,4) NOT NULL DEFAULT 0.0000,
            coupon_code varchar(64) NOT NULL DEFAULT '',
            error_message text NULL,
            meta longtext NULL,
            provisioned_at datetime NULL,
            renews_at datetime NULL,
            terminated_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY wc_order_id (wc_order_id),
            KEY wc_order_item_id (wc_order_item_id),
            KEY user_id (user_id),
            KEY provider_id (provider_id),
            KEY remote_vm_id (remote_vm_id),
            KEY status (status),
            KEY provisioning_status (provisioning_status),
            KEY next_retry_at (next_retry_at),
            KEY renews_at (renews_at)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Application, API and audit log.
     */
    private function vmLogs(): string
    {
        $table = $this->tables->name(TableRegistry::VM_LOGS);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            level varchar(20) NOT NULL DEFAULT 'info',
            channel varchar(32) NOT NULL DEFAULT 'system',
            message text NULL,
            context longtext NULL,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            vm_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            method varchar(10) NOT NULL DEFAULT '',
            endpoint varchar(255) NOT NULL DEFAULT '',
            status_code smallint(5) unsigned NOT NULL DEFAULT 0,
            duration_ms int(10) unsigned NOT NULL DEFAULT 0,
            ip_address varchar(45) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY level (level),
            KEY channel (channel),
            KEY provider_id (provider_id),
            KEY vm_order_id (vm_order_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Persistent cache for backend API responses.
     */
    private function apiCache(): string
    {
        $table = $this->tables->name(TableRegistry::API_CACHE);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            cache_key varchar(191) NOT NULL DEFAULT '',
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            payload longtext NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY cache_key (cache_key),
            KEY provider_id (provider_id),
            KEY expires_at (expires_at)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * History of catalogue synchronisation runs.
     */
    private function syncRuns(): string
    {
        $table = $this->tables->name(TableRegistry::SYNC_RUNS);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            resource varchar(32) NOT NULL DEFAULT '',
            status varchar(20) NOT NULL DEFAULT 'running',
            added int(10) unsigned NOT NULL DEFAULT 0,
            updated int(10) unsigned NOT NULL DEFAULT 0,
            removed int(10) unsigned NOT NULL DEFAULT 0,
            unchanged int(10) unsigned NOT NULL DEFAULT 0,
            message text NULL,
            context longtext NULL,
            duration_ms int(10) unsigned NOT NULL DEFAULT 0,
            started_at datetime NOT NULL,
            finished_at datetime NULL,
            PRIMARY KEY  (id),
            KEY provider_id (provider_id),
            KEY resource (resource),
            KEY status (status),
            KEY started_at (started_at)
        ) {$this->tables->charsetCollate()};";
    }

    /**
     * Link between a WordPress user and the backend account used on their behalf.
     */
    private function customerAccounts(): string
    {
        $table = $this->tables->name(TableRegistry::CUSTOMER_ACCOUNTS);

        return "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            provider_id bigint(20) unsigned NOT NULL DEFAULT 0,
            remote_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            email varchar(191) NOT NULL DEFAULT '',
            password text NULL,
            token longtext NULL,
            token_expires_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            last_login_at datetime NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_provider (user_id,provider_id),
            KEY provider_id (provider_id),
            KEY email (email),
            KEY remote_user_id (remote_user_id),
            KEY status (status)
        ) {$this->tables->charsetCollate()};";
    }
}
