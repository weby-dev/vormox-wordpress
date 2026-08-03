<?php

/**
 * Admin notices.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Admin;

defined('ABSPATH') || exit;

/**
 * Flash messages that survive the redirect after a form submission.
 *
 * Notices are stored per user so one administrator never sees the result of
 * another administrator's action.
 */
final class Notices
{
    private const TRANSIENT_PREFIX = 'cvm_notices_';
    private const TTL = 60;

    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';

    /**
     * Queue a notice for the current user.
     */
    public function add(string $type, string $message): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0 || $message === '') {
            return;
        }

        $notices = $this->pending($userId);
        $notices[] = [
            'type' => $this->normalizeType($type),
            'message' => $message,
        ];

        set_transient(self::TRANSIENT_PREFIX . $userId, $notices, self::TTL);
    }

    public function success(string $message): void
    {
        $this->add(self::TYPE_SUCCESS, $message);
    }

    public function error(string $message): void
    {
        $this->add(self::TYPE_ERROR, $message);
    }

    public function warning(string $message): void
    {
        $this->add(self::TYPE_WARNING, $message);
    }

    /**
     * Print and clear the queued notices.
     */
    public function render(): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return;
        }

        $notices = $this->pending($userId);

        if ($notices === []) {
            return;
        }

        delete_transient(self::TRANSIENT_PREFIX . $userId);

        foreach ($notices as $notice) {
            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr((string) $notice['type']),
                esc_html((string) $notice['message'])
            );
        }
    }

    /**
     * @return array<int, array{type: string, message: string}>
     */
    private function pending(int $userId): array
    {
        $notices = get_transient(self::TRANSIENT_PREFIX . $userId);

        return is_array($notices) ? $notices : [];
    }

    private function normalizeType(string $type): string
    {
        $allowed = [self::TYPE_SUCCESS, self::TYPE_ERROR, self::TYPE_WARNING, self::TYPE_INFO];

        return in_array($type, $allowed, true) ? $type : self::TYPE_INFO;
    }
}
