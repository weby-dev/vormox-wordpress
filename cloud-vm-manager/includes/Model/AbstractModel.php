<?php

/**
 * Base model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

use CloudVmManager\Contracts\ModelInterface;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use JsonSerializable;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Typed entity hydrated from a database row.
 *
 * Values are cast on the way in (`int`, `float`, `bool`, `json`) and cast back to
 * their storage representation by {@see AbstractModel::toDatabase()}, so callers
 * never deal with the string values MySQL hands back.
 */
abstract class AbstractModel implements ModelInterface, JsonSerializable
{
    /**
     * Cast attributes keyed by column name.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [];

    /**
     * Attribute snapshot taken at hydration time.
     *
     * @var array<string, mixed>
     */
    protected $original = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }

    /**
     * Column to cast type map. Supported casts: int, float, bool, json, string.
     *
     * @return array<string, string>
     */
    abstract public function casts(): array;

    /**
     * @param array<string, mixed> $attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[(string) $key] = $this->castValue((string) $key, $value);
        }

        return $this;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : $default;
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): self
    {
        $this->attributes[$key] = $this->castValue($key, $value);

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function id(): int
    {
        return (int) $this->get('id', 0);
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
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

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (bool) $value : $default;
    }

    /**
     * @return array<mixed>
     */
    public function getArray(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? $value : [];
    }

    /**
     * Nullable datetime accessor. Stored values are UTC.
     */
    public function getDateTime(string $key): ?DateTimeImmutable
    {
        $value = $this->get($key);

        if (!is_string($value) || $value === '' || strpos($value, '0000-00-00') === 0) {
            return null;
        }

        try {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(): array
    {
        $casts = $this->casts();
        $data = [];

        foreach ($this->attributes as $key => $value) {
            $cast = $casts[$key] ?? 'string';

            if ($cast === 'json') {
                $data[$key] = $value === null ? null : (string) wp_json_encode($value);

                continue;
            }

            if ($cast === 'bool') {
                $data[$key] = $value ? 1 : 0;

                continue;
            }

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Attributes changed since hydration.
     *
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function isDirty(): bool
    {
        return $this->getDirty() !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    protected function castValue(string $key, $value)
    {
        $casts = $this->casts();

        if (!isset($casts[$key])) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        switch ($casts[$key]) {
            case 'int':
                return is_numeric($value) ? (int) $value : 0;
            case 'float':
                return is_numeric($value) ? (float) $value : 0.0;
            case 'bool':
                return (bool) $value;
            case 'json':
                if (is_array($value)) {
                    return $value;
                }

                $decoded = is_string($value) && $value !== '' ? json_decode($value, true) : null;

                return is_array($decoded) ? $decoded : [];
            case 'string':
            default:
                return is_scalar($value) ? (string) $value : $value;
        }
    }
}
