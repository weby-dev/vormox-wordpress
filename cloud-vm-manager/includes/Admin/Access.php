<?php

/**
 * Admin access control.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

defined('ABSPATH') || exit;

/**
 * Single place where the capability guarding every admin screen is decided.
 */
final class Access
{
    /**
     * Capability required to manage the plugin.
     */
    public const CAPABILITY = 'manage_options';

    /**
     * Nonce action shared by the admin AJAX endpoints.
     */
    public const AJAX_NONCE = 'cvm_admin_ajax';

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Capability required to manage the plugin, filterable for custom roles.
     */
    public static function capability(): string
    {
        $capability = apply_filters('cloud_vm_manager_admin_capability', self::CAPABILITY);

        return is_string($capability) && $capability !== '' ? $capability : self::CAPABILITY;
    }

    /**
     * Whether the current user may manage the plugin.
     */
    public static function granted(): bool
    {
        return current_user_can(self::capability());
    }

    /**
     * Stop the request when the current user may not manage the plugin.
     */
    public static function assert(): void
    {
        if (self::granted()) {
            return;
        }

        wp_die(
            esc_html__('You are not allowed to manage cloud providers.', 'cloud-vm-manager'),
            esc_html__('Insufficient permissions', 'cloud-vm-manager'),
            ['response' => 403]
        );
    }
}
