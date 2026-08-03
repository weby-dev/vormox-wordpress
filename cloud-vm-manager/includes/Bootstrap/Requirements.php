<?php

/**
 * Environment requirement checks.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Bootstrap;

defined('ABSPATH') || exit;

/**
 * Validates the hosting environment before the plugin boots.
 *
 * Hard requirements (PHP version, WordPress version, PHP extensions) prevent the
 * plugin from booting. WooCommerce is treated as a soft requirement: the plugin
 * still boots so that its settings remain reachable, but the store integration
 * stays disabled and an admin notice is displayed.
 *
 * Failures are collected as codes and only turned into translated sentences when
 * they are rendered, so no translation is loaded before `init`.
 */
final class Requirements
{
    private const FAILURE_PHP = 'php';
    private const FAILURE_WORDPRESS = 'wordpress';
    private const FAILURE_EXTENSION = 'extension';

    /**
     * @var string
     */
    private $minimumPhp;

    /**
     * @var string
     */
    private $minimumWordPress;

    /**
     * @var string
     */
    private $minimumWooCommerce;

    /**
     * @var string[]
     */
    private $requiredExtensions;

    /**
     * @var array<int, array{code: string, actual: string, expected: string}>|null
     */
    private $failures;

    /**
     * @param string[] $requiredExtensions
     */
    public function __construct(
        string $minimumPhp = CVM_MINIMUM_PHP,
        string $minimumWordPress = CVM_MINIMUM_WP,
        string $minimumWooCommerce = CVM_MINIMUM_WC,
        array $requiredExtensions = ['json', 'openssl']
    ) {
        $this->minimumPhp = $minimumPhp;
        $this->minimumWordPress = $minimumWordPress;
        $this->minimumWooCommerce = $minimumWooCommerce;
        $this->requiredExtensions = $requiredExtensions;
    }

    /**
     * Whether every hard requirement is met.
     */
    public function isSatisfied(): bool
    {
        return $this->failures() === [];
    }

    /**
     * Unmet hard requirements as structured data.
     *
     * @return array<int, array{code: string, actual: string, expected: string}>
     */
    public function failures(): array
    {
        if ($this->failures !== null) {
            return $this->failures;
        }

        $failures = [];

        if (version_compare(PHP_VERSION, $this->minimumPhp, '<')) {
            $failures[] = [
                'code' => self::FAILURE_PHP,
                'actual' => PHP_VERSION,
                'expected' => $this->minimumPhp,
            ];
        }

        $wordPressVersion = (string) get_bloginfo('version');

        if (version_compare($wordPressVersion, $this->minimumWordPress, '<')) {
            $failures[] = [
                'code' => self::FAILURE_WORDPRESS,
                'actual' => $wordPressVersion,
                'expected' => $this->minimumWordPress,
            ];
        }

        foreach ($this->requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $failures[] = [
                    'code' => self::FAILURE_EXTENSION,
                    'actual' => '',
                    'expected' => $extension,
                ];
            }
        }

        $this->failures = $failures;

        return $this->failures;
    }

    /**
     * Unmet hard requirements as translated sentences.
     *
     * Only call this while rendering, never during `plugins_loaded`.
     *
     * @return string[]
     */
    public function errors(): array
    {
        $messages = [];

        foreach ($this->failures() as $failure) {
            $messages[] = $this->describe($failure);
        }

        return $messages;
    }

    /**
     * Whether an active WooCommerce installation of a supported version is present.
     */
    public function hasWooCommerce(): bool
    {
        if (!class_exists('WooCommerce') || !defined('WC_VERSION')) {
            return false;
        }

        return version_compare((string) constant('WC_VERSION'), $this->minimumWooCommerce, '>=');
    }

    /**
     * Human readable WooCommerce requirement message.
     */
    public function wooCommerceNotice(): string
    {
        /* translators: %s: required WooCommerce version. */
        $message = __(
            'Cloud VM Manager needs WooCommerce %s or newer. Store features are disabled until it is active.',
            'cloud-vm-manager'
        );

        return sprintf($message, $this->minimumWooCommerce);
    }

    /**
     * Display the hard requirement failures in the WordPress admin.
     */
    public function registerAdminNotices(): void
    {
        if ($this->isSatisfied()) {
            return;
        }

        add_action(
            'admin_notices',
            function (): void {
                if (!current_user_can('activate_plugins')) {
                    return;
                }

                echo '<div class="notice notice-error"><p>';
                echo esc_html(implode(' ', $this->errors()));
                echo '</p></div>';
            }
        );
    }

    /**
     * @param array{code: string, actual: string, expected: string} $failure
     */
    private function describe(array $failure): string
    {
        switch ($failure['code']) {
            case self::FAILURE_PHP:
                /* translators: 1: required PHP version, 2: current PHP version. */
                $template = __(
                    'Cloud VM Manager requires PHP %1$s or newer. This server runs PHP %2$s.',
                    'cloud-vm-manager'
                );

                return sprintf($template, $failure['expected'], $failure['actual']);
            case self::FAILURE_WORDPRESS:
                /* translators: 1: required WordPress version, 2: current WordPress version. */
                $template = __(
                    'Cloud VM Manager requires WordPress %1$s or newer. This site runs WordPress %2$s.',
                    'cloud-vm-manager'
                );

                return sprintf($template, $failure['expected'], $failure['actual']);
            default:
                /* translators: %s: PHP extension name. */
                $template = __(
                    'Cloud VM Manager requires the PHP extension "%s" to be enabled.',
                    'cloud-vm-manager'
                );

                return sprintf($template, $failure['expected']);
        }
    }
}
