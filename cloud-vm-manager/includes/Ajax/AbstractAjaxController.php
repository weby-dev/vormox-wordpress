<?php

/**
 * Base AJAX controller.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Ajax;

defined('ABSPATH') || exit;

/**
 * Shared guards and helpers for the AJAX endpoints.
 *
 * Every endpoint verifies a nonce and a capability before it reads a single
 * request value, and all input is read through the typed accessors below so no
 * raw superglobal reaches a service.
 */
abstract class AbstractAjaxController
{
    /**
     * Register the WordPress AJAX actions of the controller.
     */
    abstract public function register(): void;

    /**
     * Verify the nonce and the capability, terminating the request on failure.
     */
    protected function authorize(string $nonceAction, string $capability): void
    {
        check_ajax_referer($nonceAction, 'nonce');

        if (!current_user_can($capability)) {
            $this->failure(__('You are not allowed to perform this action.', 'cloud-vm-manager'), 403);
        }
    }

    /**
     * Send a success payload and terminate.
     *
     * @param array<string, mixed> $data
     */
    protected function success(array $data = []): void
    {
        wp_send_json_success($data);
    }

    /**
     * Send an error payload and terminate.
     *
     * @param array<string, mixed> $data
     */
    protected function failure(string $message, int $status = 400, array $data = []): void
    {
        wp_send_json_error(array_merge(['message' => $message], $data), $status);
    }

    /**
     * Read a positive integer from the request.
     */
    protected function intParam(string $key, int $default = 0): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
        if (!isset($_REQUEST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
        return absint(wp_unslash((string) $_REQUEST[$key]));
    }

    /**
     * Read a sanitised text value from the request.
     */
    protected function textParam(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
        if (!isset($_REQUEST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
        return sanitize_text_field(wp_unslash((string) $_REQUEST[$key]));
    }

    /**
     * Read a key style value from the request, for example an action name.
     */
    protected function keyParam(string $key, string $default = ''): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
        if (!isset($_REQUEST[$key])) {
            return $default;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
        return sanitize_key(wp_unslash((string) $_REQUEST[$key]));
    }
}
