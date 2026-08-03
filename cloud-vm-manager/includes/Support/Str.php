<?php

/**
 * String helper.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Support;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Static string helpers used across the plugin.
 */
final class Str
{
    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === $needle;
    }

    public static function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && strpos($haystack, $needle) !== false;
    }

    /**
     * Mask a secret, keeping a few readable characters at the end.
     */
    public static function mask(string $value, int $visible = 4, string $character = '*'): string
    {
        $length = strlen($value);

        if ($length === 0) {
            return '';
        }

        if ($length <= $visible) {
            return str_repeat($character, $length);
        }

        return str_repeat($character, $length - $visible) . substr($value, -$visible);
    }

    /**
     * Shorten a string for log output.
     */
    public static function truncate(string $value, int $length = 500, string $suffix = '…'): string
    {
        if ($length <= 0 || strlen($value) <= $length) {
            return $value;
        }

        return rtrim(substr($value, 0, $length)) . $suffix;
    }

    /**
     * Join URL segments with a single slash between them.
     */
    public static function joinUrl(string $base, string $path): string
    {
        if ($path === '') {
            return rtrim($base, '/');
        }

        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Checksum used to detect catalogue changes.
     *
     * @param mixed $value
     */
    public static function checksum($value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = (string) wp_json_encode($value);
        }

        return md5((string) $value);
    }

    /**
     * Convert a snake_case or kebab-case value to a readable label.
     */
    public static function humanize(string $value): string
    {
        $value = str_replace(['-', '_'], ' ', $value);

        return ucwords(trim($value));
    }

    /**
     * Generate a random password that satisfies common provider policies.
     */
    public static function randomPassword(int $length = 16): string
    {
        $length = max(12, $length);

        $sets = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            '!@#$%^&*-_=+',
        ];

        $password = '';

        foreach ($sets as $set) {
            $password .= $set[random_int(0, strlen($set) - 1)];
        }

        $all = implode('', $sets);

        while (strlen($password) < $length) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }
}
