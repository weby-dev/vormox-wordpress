<?php

/**
 * Settings field definitions.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

defined('ABSPATH') || exit;

/**
 * Presentation metadata for the plugin settings.
 *
 * The storage schema lives in `Support\Settings`; this class only describes how
 * each key is labelled and rendered, so the two concerns stay separate.
 */
final class SettingsFields
{
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_NUMBER = 'number';
    public const TYPE_SELECT = 'select';
    public const TYPE_TEXT = 'text';

    /**
     * Field groups, each with its label and its fields.
     *
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public function groups(): array
    {
        return [
            [
                'title' => __('API connection', 'cloud-vm-manager'),
                'description' => __('Applies to every request the plugin sends to a provider.', 'cloud-vm-manager'),
                'fields' => [
                    [
                        'key' => 'api_timeout',
                        'label' => __('Request timeout', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('seconds', 'cloud-vm-manager'),
                        'min' => 5,
                        'max' => 120,
                        'description' => __('A provider may override this with its own timeout.', 'cloud-vm-manager'),
                    ],
                    [
                        'key' => 'api_retries',
                        'label' => __('Retries', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'min' => 0,
                        'max' => 5,
                        'description' => __(
                            'Extra attempts after a network error or a temporary server error.',
                            'cloud-vm-manager'
                        ),
                    ],
                    [
                        'key' => 'api_retry_delay',
                        'label' => __('Retry delay', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('seconds', 'cloud-vm-manager'),
                        'min' => 1,
                        'max' => 10,
                        'description' => __(
                            'Waiting time before the next attempt, multiplied by the attempt number.',
                            'cloud-vm-manager'
                        ),
                    ],
                ],
            ],
            [
                'title' => __('Synchronisation', 'cloud-vm-manager'),
                'description' => __(
                    'How often the provider catalogue is refreshed in the background.',
                    'cloud-vm-manager'
                ),
                'fields' => [
                    [
                        'key' => 'auto_sync_enabled',
                        'label' => __('Automatic synchronisation', 'cloud-vm-manager'),
                        'type' => self::TYPE_CHECKBOX,
                        'checkbox_label' => __('Keep the catalogue in sync on a schedule', 'cloud-vm-manager'),
                    ],
                    [
                        'key' => 'sync_interval',
                        'label' => __('Synchronisation interval', 'cloud-vm-manager'),
                        'type' => self::TYPE_SELECT,
                        'options' => $this->scheduleOptions(),
                    ],
                    [
                        'key' => 'sync_retention_days',
                        'label' => __('Keep sync history', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('days', 'cloud-vm-manager'),
                        'min' => 1,
                        'max' => 365,
                    ],
                ],
            ],
            [
                'title' => __('Pricing', 'cloud-vm-manager'),
                'description' => __('Applied to newly synchronised catalogue entries.', 'cloud-vm-manager'),
                'fields' => [
                    [
                        'key' => 'default_markup_type',
                        'label' => __('Default markup type', 'cloud-vm-manager'),
                        'type' => self::TYPE_SELECT,
                        'options' => [
                            'percent' => __('Percentage', 'cloud-vm-manager'),
                            'fixed' => __('Fixed amount', 'cloud-vm-manager'),
                        ],
                    ],
                    [
                        'key' => 'default_markup_value',
                        'label' => __('Default markup value', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'min' => 0,
                        'max' => 1000,
                        'step' => '0.01',
                    ],
                ],
            ],
            [
                'title' => __('Provisioning', 'cloud-vm-manager'),
                'description' => __(
                    'Retry behaviour when a machine cannot be created immediately.',
                    'cloud-vm-manager'
                ),
                'fields' => [
                    [
                        'key' => 'provisioning_retry_limit',
                        'label' => __('Maximum attempts', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'min' => 1,
                        'max' => 20,
                    ],
                    [
                        'key' => 'provisioning_retry_delay',
                        'label' => __('Delay between attempts', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('seconds', 'cloud-vm-manager'),
                        'min' => 30,
                        'max' => 3600,
                    ],
                ],
            ],
            [
                'title' => __('Customer dashboard', 'cloud-vm-manager'),
                'description' => __('Frontend behaviour for logged in customers.', 'cloud-vm-manager'),
                'fields' => [
                    [
                        'key' => 'enable_client_dashboard',
                        'label' => __('Customer dashboard', 'cloud-vm-manager'),
                        'type' => self::TYPE_CHECKBOX,
                        'checkbox_label' => __(
                            'Let customers manage their machines from the frontend',
                            'cloud-vm-manager'
                        ),
                    ],
                    [
                        'key' => 'metrics_refresh_interval',
                        'label' => __('Metrics refresh interval', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('seconds', 'cloud-vm-manager'),
                        'min' => 10,
                        'max' => 600,
                    ],
                ],
            ],
            [
                'title' => __('Performance', 'cloud-vm-manager'),
                'description' => __('Caching of catalogue and machine responses.', 'cloud-vm-manager'),
                'fields' => [
                    [
                        'key' => 'cache_enabled',
                        'label' => __('Response cache', 'cloud-vm-manager'),
                        'type' => self::TYPE_CHECKBOX,
                        'checkbox_label' => __('Cache API responses', 'cloud-vm-manager'),
                    ],
                    [
                        'key' => 'cache_ttl',
                        'label' => __('Cache lifetime', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('seconds', 'cloud-vm-manager'),
                        'min' => 30,
                        'max' => 86400,
                    ],
                ],
            ],
            [
                'title' => __('Logging', 'cloud-vm-manager'),
                'description' => __('What the plugin records and for how long.', 'cloud-vm-manager'),
                'fields' => [
                    [
                        'key' => 'debug_mode',
                        'label' => __('Debug mode', 'cloud-vm-manager'),
                        'type' => self::TYPE_CHECKBOX,
                        'checkbox_label' => __('Record every request and response', 'cloud-vm-manager'),
                        'description' => __(
                            'Credentials are always redacted before an entry is stored.',
                            'cloud-vm-manager'
                        ),
                    ],
                    [
                        'key' => 'log_level',
                        'label' => __('Minimum level', 'cloud-vm-manager'),
                        'type' => self::TYPE_SELECT,
                        'options' => [
                            'debug' => __('Debug', 'cloud-vm-manager'),
                            'info' => __('Info', 'cloud-vm-manager'),
                            'notice' => __('Notice', 'cloud-vm-manager'),
                            'warning' => __('Warning', 'cloud-vm-manager'),
                            'error' => __('Error', 'cloud-vm-manager'),
                        ],
                        'description' => __('Debug mode overrides this and records everything.', 'cloud-vm-manager'),
                    ],
                    [
                        'key' => 'log_retention_days',
                        'label' => __('Keep log entries', 'cloud-vm-manager'),
                        'type' => self::TYPE_NUMBER,
                        'suffix' => __('days', 'cloud-vm-manager'),
                        'min' => 1,
                        'max' => 365,
                    ],
                ],
            ],
            [
                'title' => __('Uninstall', 'cloud-vm-manager'),
                'description' => __('What happens when the plugin is deleted.', 'cloud-vm-manager'),
                'fields' => [
                    [
                        'key' => 'delete_data_on_uninstall',
                        'label' => __('Delete data', 'cloud-vm-manager'),
                        'type' => self::TYPE_CHECKBOX,
                        'checkbox_label' => __(
                            'Remove all plugin tables and settings on uninstall',
                            'cloud-vm-manager'
                        ),
                        'description' => __(
                            'Leave this off to keep providers, orders and logs when reinstalling.',
                            'cloud-vm-manager'
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * Every settings key that belongs to a rendered field.
     *
     * @return string[]
     */
    public function keys(): array
    {
        $keys = [];

        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $field) {
                $keys[] = (string) $field['key'];
            }
        }

        return $keys;
    }

    /**
     * Keys rendered as a checkbox, which browsers omit when unchecked.
     *
     * @return string[]
     */
    public function checkboxKeys(): array
    {
        $keys = [];

        foreach ($this->groups() as $group) {
            foreach ($group['fields'] as $field) {
                if (($field['type'] ?? '') === self::TYPE_CHECKBOX) {
                    $keys[] = (string) $field['key'];
                }
            }
        }

        return $keys;
    }

    /**
     * Available cron schedules, labelled for the select field.
     *
     * @return array<string, string>
     */
    private function scheduleOptions(): array
    {
        $options = [];
        $schedules = wp_get_schedules();

        if (!is_array($schedules)) {
            return ['twicedaily' => __('Twice daily', 'cloud-vm-manager')];
        }

        foreach ($schedules as $slug => $schedule) {
            $label = isset($schedule['display']) ? (string) $schedule['display'] : (string) $slug;
            $options[(string) $slug] = $label;
        }

        return $options;
    }
}
