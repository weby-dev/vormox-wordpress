<?php

/**
 * Log levels.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Logging;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * PSR-3 severity levels and their relative priority.
 */
final class LogLevel
{
    public const EMERGENCY = 'emergency';
    public const ALERT = 'alert';
    public const CRITICAL = 'critical';
    public const ERROR = 'error';
    public const WARNING = 'warning';
    public const NOTICE = 'notice';
    public const INFO = 'info';
    public const DEBUG = 'debug';

    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Every level, most severe first.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG,
        ];
    }

    /**
     * Numeric priority: a lower number means a more severe level.
     */
    public static function priority(string $level): int
    {
        $priorities = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7,
        ];

        return $priorities[$level] ?? 6;
    }

    public static function isValid(string $level): bool
    {
        return in_array($level, self::all(), true);
    }

    /**
     * Coerce an arbitrary value into a known level.
     */
    public static function normalize(string $level): string
    {
        $level = strtolower(trim($level));

        return self::isValid($level) ? $level : self::INFO;
    }

    /**
     * Whether the level is at least as severe as the configured threshold.
     */
    public static function passes(string $level, string $threshold): bool
    {
        return self::priority($level) <= self::priority($threshold);
    }
}
