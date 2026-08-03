<?php

/**
 * Provider model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * A cloud provider the store sells virtual machines for.
 *
 * Credential columns hold ciphertext; decryption is the responsibility of the
 * service layer so that a model instance can never leak a secret by accident.
 */
final class Provider extends AbstractModel
{
    public const STATUS_CONNECTED = 'connected';
    public const STATUS_DISCONNECTED = 'disconnected';
    public const STATUS_ERROR = 'error';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'name' => 'string',
            'slug' => 'string',
            'host_url' => 'string',
            'api_url' => 'string',
            'email' => 'string',
            'password' => 'string',
            'api_token' => 'string',
            'token_expires_at' => 'string',
            'verify_ssl' => 'bool',
            'timeout' => 'int',
            'region' => 'string',
            'description' => 'string',
            'status' => 'string',
            'platform_version' => 'string',
            'currency' => 'string',
            'is_active' => 'bool',
            'is_default' => 'bool',
            'sort_order' => 'int',
            'last_error' => 'string',
            'last_connected_at' => 'string',
            'last_synced_at' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
        ];
    }

    public function getName(): string
    {
        return $this->getString('name');
    }

    public function getSlug(): string
    {
        return $this->getString('slug');
    }

    public function getHostUrl(): string
    {
        return $this->getString('host_url');
    }

    public function getApiUrl(): string
    {
        return $this->getString('api_url');
    }

    public function getEmail(): string
    {
        return $this->getString('email');
    }

    /**
     * Encrypted password as stored.
     */
    public function getEncryptedPassword(): string
    {
        return $this->getString('password');
    }

    /**
     * Encrypted API token as stored.
     */
    public function getEncryptedToken(): string
    {
        return $this->getString('api_token');
    }

    public function hasToken(): bool
    {
        return $this->getEncryptedToken() !== '';
    }

    /**
     * Whether the stored token is missing or expires within the grace period.
     *
     * @param int $graceSeconds Seconds before expiry at which a token counts as expired.
     */
    public function isTokenExpired(int $graceSeconds = 300): bool
    {
        if (!$this->hasToken()) {
            return true;
        }

        $expiry = $this->getDateTime('token_expires_at');

        if ($expiry === null) {
            return false;
        }

        return $expiry->getTimestamp() - $graceSeconds <= time();
    }

    public function getStatus(): string
    {
        return $this->getString('status', self::STATUS_DISCONNECTED);
    }

    public function isConnected(): bool
    {
        return $this->getStatus() === self::STATUS_CONNECTED;
    }

    public function isActive(): bool
    {
        return $this->getBool('is_active', true);
    }

    public function isDefault(): bool
    {
        return $this->getBool('is_default');
    }

    public function verifySsl(): bool
    {
        return $this->getBool('verify_ssl', true);
    }

    public function getTimeout(): int
    {
        $timeout = $this->getInt('timeout', 30);

        return $timeout > 0 ? $timeout : 30;
    }

    public function getRegion(): string
    {
        return $this->getString('region');
    }

    public function getDescription(): string
    {
        return $this->getString('description');
    }

    public function getPlatformVersion(): string
    {
        return $this->getString('platform_version');
    }

    public function getCurrency(): string
    {
        return $this->getString('currency');
    }

    public function getLastError(): string
    {
        return $this->getString('last_error');
    }

    /**
     * Base URL of the REST API without a trailing slash.
     */
    public function getApiBaseUrl(): string
    {
        return rtrim($this->getApiUrl(), '/');
    }
}
