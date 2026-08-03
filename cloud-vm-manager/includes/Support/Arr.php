<?php

/**
 * Array helper.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Support;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Static helpers for reading nested API payloads.
 */
final class Arr
{
    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Read a value using dot notation, for example "current.status".
     *
     * @param array<string, mixed>|array<int, mixed> $array
     * @param mixed                                  $default
     *
     * @return mixed
     */
    public static function get(array $array, string $key, $default = null)
    {
        if ($key === '') {
            return $default;
        }

        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $value = $array;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Read the first key that exists.
     *
     * @param array<string, mixed> $array
     * @param string[]             $keys
     * @param mixed                $default
     *
     * @return mixed
     */
    public static function first(array $array, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            $value = self::get($array, $key);

            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Whether every given key exists.
     *
     * @param array<string, mixed> $array
     * @param string[]             $keys
     */
    public static function hasAll(array $array, array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::get($array, $key) === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Keep only the listed keys.
     *
     * @param array<string, mixed> $array
     * @param string[]             $keys
     *
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Remove the listed keys.
     *
     * @param array<string, mixed> $array
     * @param string[]             $keys
     *
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    /**
     * Collect one column of a list of rows.
     *
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, mixed>
     */
    public static function pluck(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            if (is_array($row) && array_key_exists($key, $row)) {
                $values[] = $row[$key];
            }
        }

        return $values;
    }

    /**
     * Whether the array is a zero indexed list.
     *
     * @param array<mixed> $array
     */
    public static function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Convert an integer list, dropping anything that is not numeric.
     *
     * @param array<int, mixed> $values
     *
     * @return int[]
     */
    public static function toIntList(array $values): array
    {
        $integers = [];

        foreach ($values as $value) {
            if (is_numeric($value)) {
                $integers[] = (int) $value;
            }
        }

        return $integers;
    }
}
