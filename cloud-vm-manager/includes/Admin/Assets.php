<?php

/**
 * Admin assets.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

defined('ABSPATH') || exit;

/**
 * Registers the stylesheet and script of the admin screens.
 *
 * Assets are only enqueued on the plugin screens so the rest of the admin stays
 * untouched.
 */
final class Assets
{
    public const HANDLE = 'cloud-vm-manager-admin';

    /**
     * Screen hooks the assets are loaded on.
     *
     * @var string[]
     */
    private $screens = [];

    /**
     * @param string[] $screens
     */
    public function setScreens(array $screens): void
    {
        $this->screens = $screens;
    }

    /**
     * Enqueue the assets when the current screen belongs to the plugin.
     */
    public function enqueue(string $hook): void
    {
        if (!in_array($hook, $this->screens, true)) {
            return;
        }

        wp_enqueue_style(
            self::HANDLE,
            CVM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            CVM_VERSION
        );

        wp_enqueue_script(
            self::HANDLE,
            CVM_PLUGIN_URL . 'assets/js/admin.js',
            [],
            CVM_VERSION,
            true
        );

        wp_localize_script(
            self::HANDLE,
            'cvmAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(Access::AJAX_NONCE),
                'i18n' => [
                    'working' => __('Working…', 'cloud-vm-manager'),
                    'testing' => __('Testing connection…', 'cloud-vm-manager'),
                    'connecting' => __('Connecting…', 'cloud-vm-manager'),
                    'disconnecting' => __('Disconnecting…', 'cloud-vm-manager'),
                    'refreshing' => __('Refreshing token…', 'cloud-vm-manager'),
                    'syncing' => __('Synchronising catalogue…', 'cloud-vm-manager'),
                    'unexpectedError' => __('The request could not be completed.', 'cloud-vm-manager'),
                    'confirmDisconnect' => __(
                        'Disconnect this provider? Stored tokens will be removed.',
                        'cloud-vm-manager'
                    ),
                    'connected' => __('Connected', 'cloud-vm-manager'),
                    'disconnected' => __('Disconnected', 'cloud-vm-manager'),
                    'error' => __('Error', 'cloud-vm-manager'),
                    /* translators: %s: formatted size. */
                    'usageDisk' => __('Disk %s', 'cloud-vm-manager'),
                    /* translators: %s: formatted size. */
                    'usageTransfer' => __('Transfer %s', 'cloud-vm-manager'),
                ],
            ]
        );
    }
}
