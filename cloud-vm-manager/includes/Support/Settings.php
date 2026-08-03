<?php

/**
 * Plugin settings.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Support;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Typed access to the plugin option.
 *
 * Every setting is declared with a default and a sanitiser, so an unknown or
 * malformed key can never reach the database or a template.
 */
final class Settings
{
    public const OPTION_KEY = 'cvm_settings';

    /**
     * In request cache of the stored option.
     *
     * @var array<string, mixed>|null
     */
    private $cache;

    /**
     * Declared settings: key => [default, sanitiser].
     *
     * @return array<string, array{0: mixed, 1: string}>
     */
    public function schema(): array
    {
        return [
            'debug_mode' => [false, 'bool'],
            'log_level' => ['info', 'log_level'],
            'log_retention_days' => [30, 'int'],
            'sync_retention_days' => [30, 'int'],
            'cache_enabled' => [true, 'bool'],
            'cache_ttl' => [300, 'int'],
            'api_timeout' => [30, 'int'],
            'api_retries' => [2, 'int'],
            'api_retry_delay' => [2, 'int'],
            'sync_interval' => ['twicedaily', 'schedule'],
            'auto_sync_enabled' => [true, 'bool'],
            'provisioning_retry_limit' => [5, 'int'],
            'provisioning_retry_delay' => [300, 'int'],
            'provisioning_gateway' => ['CASHFREE', 'gateway'],
            'use_wallet_balance' => [true, 'bool'],
            'default_markup_type' => ['percent', 'markup_type'],
            'default_markup_value' => [0.0, 'float'],
            'dashboard_page_id' => [0, 'int'],
            'metrics_refresh_interval' => [30, 'int'],
            'enable_client_dashboard' => [true, 'bool'],
            'delete_data_on_uninstall' => [false, 'bool'],
        ];
    }

    /**
     * Every setting with its stored or default value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];

        $settings = [];

        foreach ($this->schema() as $key => $definition) {
            $settings[$key] = array_key_exists($key, $stored)
                ? $this->sanitize($key, $stored[$key])
                : $definition[0];
        }

        $this->cache = $settings;

        return $settings;
    }

    /**
     * @param mixed $default Returned when the key is not declared.
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $settings = $this->all();

        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (bool) $value : $default;
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * Write a single setting.
     *
     * @param mixed $value
     */
    public function set(string $key, $value): bool
    {
        return $this->update([$key => $value]);
    }

    /**
     * Write several settings at once, ignoring undeclared keys.
     *
     * @param array<string, mixed> $values
     */
    public function update(array $values): bool
    {
        $settings = $this->all();
        $schema = $this->schema();

        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (!isset($schema[$key])) {
                continue;
            }

            $settings[$key] = $this->sanitize($key, $value);
        }

        $this->cache = $settings;

        return update_option(self::OPTION_KEY, $settings, 'yes');
    }

    /**
     * Store the defaults for settings that were never written.
     */
    public function seedDefaults(): bool
    {
        $stored = get_option(self::OPTION_KEY, []);
        $stored = is_array($stored) ? $stored : [];

        $settings = [];

        foreach ($this->schema() as $key => $definition) {
            $settings[$key] = array_key_exists($key, $stored)
                ? $this->sanitize($key, $stored[$key])
                : $definition[0];
        }

        $this->cache = $settings;

        return update_option(self::OPTION_KEY, $settings, 'yes');
    }

    /**
     * Drop the in request cache so the next read hits the database.
     */
    public function flushCache(): void
    {
        $this->cache = null;
    }

    /**
     * Apply the sanitiser declared for a key.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private function sanitize(string $key, $value)
    {
        $schema = $this->schema();

        if (!isset($schema[$key])) {
            return null;
        }

        $default = $schema[$key][0];

        switch ($schema[$key][1]) {
            case 'bool':
                return (bool) $value;
            case 'int':
                return is_numeric($value) ? max(0, (int) $value) : (int) $default;
            case 'float':
                return is_numeric($value) ? (float) $value : (float) $default;
            case 'log_level':
                $level = is_string($value) ? strtolower($value) : '';

                $levels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

                return in_array($level, $levels, true) ? $level : (string) $default;
            case 'schedule':
                $schedule = is_string($value) ? sanitize_key($value) : '';

                return $schedule !== '' ? $schedule : (string) $default;
            case 'markup_type':
                return in_array($value, ['percent', 'fixed'], true) ? (string) $value : (string) $default;
            case 'gateway':
                $gateway = is_scalar($value) ? strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $value)) : '';

                return $gateway !== '' ? $gateway : (string) $default;
            default:
                return is_scalar($value) ? sanitize_text_field((string) $value) : $default;
        }
    }
}
