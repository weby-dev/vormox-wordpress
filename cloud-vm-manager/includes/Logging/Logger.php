<?php

/**
 * Logger.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Logging;

use CloudVmManager\Contracts\LoggerInterface;
use CloudVmManager\Exception\DatabaseException;
use CloudVmManager\Model\LogEntry;
use CloudVmManager\Repository\LogRepository;
use CloudVmManager\Support\Settings;
use CloudVmManager\Support\Str;
use Throwable;

defined('ABSPATH') || exit;

/**
 * Writes structured log entries to the plugin log table.
 *
 * Entries below the configured level are dropped, debug entries additionally
 * require debug mode. Context is redacted before it is stored, and a failing log
 * write never interrupts the request that produced it.
 */
final class Logger implements LoggerInterface
{
    /**
     * Reserved context keys that are stored in their own column.
     *
     * @var string[]
     */
    private const COLUMN_KEYS = [
        'channel',
        'provider_id',
        'vm_order_id',
        'wc_order_id',
        'user_id',
        'method',
        'endpoint',
        'status_code',
        'duration_ms',
    ];

    /**
     * @var LogRepository
     */
    private $repository;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @var Redactor
     */
    private $redactor;

    public function __construct(LogRepository $repository, Settings $settings, Redactor $redactor)
    {
        $this->repository = $repository;
        $this->settings = $settings;
        $this->redactor = $redactor;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an exception together with its origin.
     *
     * @param array<string, mixed> $context
     */
    public function exception(Throwable $exception, string $message = '', array $context = []): void
    {
        $context['exception'] = get_class($exception);
        $context['exception_message'] = $exception->getMessage();
        $context['file'] = $exception->getFile() . ':' . $exception->getLine();

        $this->log(
            LogLevel::ERROR,
            $message !== '' ? $message : $exception->getMessage(),
            $context
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        $level = LogLevel::normalize($level);

        if (!$this->shouldLog($level)) {
            return;
        }

        $row = [
            'level' => $level,
            'channel' => $this->resolveChannel($context),
            'message' => Str::truncate($this->redactor->redactString($message), 1000),
            'provider_id' => (int) ($context['provider_id'] ?? 0),
            'vm_order_id' => (int) ($context['vm_order_id'] ?? 0),
            'wc_order_id' => (int) ($context['wc_order_id'] ?? 0),
            'user_id' => (int) ($context['user_id'] ?? $this->currentUserId()),
            'method' => strtoupper(substr((string) ($context['method'] ?? ''), 0, 10)),
            'endpoint' => Str::truncate((string) ($context['endpoint'] ?? ''), 255, ''),
            'status_code' => (int) ($context['status_code'] ?? 0),
            'duration_ms' => (int) ($context['duration_ms'] ?? 0),
            'ip_address' => $this->clientIp(),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ];

        $extra = $this->redactor->redact($this->stripColumnKeys($context));
        $row['context'] = $extra === [] ? null : wp_json_encode($extra);

        try {
            $this->repository->insert($row);
        } catch (DatabaseException $exception) {
            $this->writeToErrorLog($level, $message);
        }

        if ($this->isDebugMode()) {
            $this->writeToErrorLog($level, $message);
        }
    }

    /**
     * Whether verbose logging is enabled.
     */
    public function isDebugMode(): bool
    {
        return $this->settings->getBool('debug_mode');
    }

    /**
     * Whether an entry of the given level is stored.
     */
    private function shouldLog(string $level): bool
    {
        return LogLevel::passes($level, $this->threshold());
    }

    /**
     * Effective severity threshold. Debug mode lowers it to the most verbose level.
     */
    private function threshold(): string
    {
        if ($this->isDebugMode()) {
            return LogLevel::DEBUG;
        }

        return LogLevel::normalize($this->settings->getString('log_level', LogLevel::INFO));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveChannel(array $context): string
    {
        $channel = (string) ($context['channel'] ?? LogEntry::CHANNEL_SYSTEM);

        return in_array($channel, LogEntry::channels(), true) ? $channel : LogEntry::CHANNEL_SYSTEM;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function stripColumnKeys(array $context): array
    {
        foreach (self::COLUMN_KEYS as $key) {
            unset($context[$key]);
        }

        return $context;
    }

    private function currentUserId(): int
    {
        return function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
    }

    /**
     * Remote address of the current request, validated and truncated.
     */
    private function clientIp(): string
    {
        $remote = isset($_SERVER['REMOTE_ADDR'])
            ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR']))
            : '';

        $filtered = filter_var($remote, FILTER_VALIDATE_IP);

        return is_string($filtered) ? substr($filtered, 0, 45) : '';
    }

    private function writeToErrorLog(string $level, string $message): void
    {
        if (!defined('WP_DEBUG_LOG') || !constant('WP_DEBUG_LOG')) {
            return;
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf('[cloud-vm-manager][%s] %s', $level, $this->redactor->redactString($message)));
    }
}
