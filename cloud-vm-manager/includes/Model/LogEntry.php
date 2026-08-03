<?php

/**
 * Log entry model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A single row of the plugin log.
 */
final class LogEntry extends AbstractModel
{
    public const CHANNEL_SYSTEM = 'system';
    public const CHANNEL_API = 'api';
    public const CHANNEL_SYNC = 'sync';
    public const CHANNEL_PROVISIONING = 'provisioning';
    public const CHANNEL_CUSTOMER = 'customer';
    public const CHANNEL_ADMIN = 'admin';
    public const CHANNEL_CRON = 'cron';

    /**
     * Every channel the plugin writes to.
     *
     * @return string[]
     */
    public static function channels(): array
    {
        return [
            self::CHANNEL_SYSTEM,
            self::CHANNEL_API,
            self::CHANNEL_SYNC,
            self::CHANNEL_PROVISIONING,
            self::CHANNEL_CUSTOMER,
            self::CHANNEL_ADMIN,
            self::CHANNEL_CRON,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'level' => 'string',
            'channel' => 'string',
            'message' => 'string',
            'context' => 'json',
            'provider_id' => 'int',
            'vm_order_id' => 'int',
            'wc_order_id' => 'int',
            'user_id' => 'int',
            'method' => 'string',
            'endpoint' => 'string',
            'status_code' => 'int',
            'duration_ms' => 'int',
            'ip_address' => 'string',
            'created_at' => 'string',
        ];
    }

    public function getLevel(): string
    {
        return $this->getString('level');
    }

    public function getChannel(): string
    {
        return $this->getString('channel');
    }

    public function getMessage(): string
    {
        return $this->getString('message');
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->getArray('context');
    }

    public function getStatusCode(): int
    {
        return $this->getInt('status_code');
    }

    public function getDurationMs(): int
    {
        return $this->getInt('duration_ms');
    }

    public function getEndpoint(): string
    {
        return $this->getString('endpoint');
    }

    public function getMethod(): string
    {
        return $this->getString('method');
    }
}
