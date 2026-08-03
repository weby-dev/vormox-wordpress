<?php

/**
 * Customer account model.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Model;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Link between a WordPress user and their backend account.
 *
 * The password and the bearer token are stored encrypted and are only decrypted
 * inside the authentication service when a request is signed.
 */
final class CustomerAccount extends AbstractModel
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_ERROR = 'error';

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'int',
            'user_id' => 'int',
            'provider_id' => 'int',
            'remote_user_id' => 'int',
            'email' => 'string',
            'password' => 'string',
            'token' => 'string',
            'token_expires_at' => 'string',
            'status' => 'string',
            'last_login_at' => 'string',
            'last_error' => 'string',
            'created_at' => 'string',
            'updated_at' => 'string',
        ];
    }

    public function getUserId(): int
    {
        return $this->getInt('user_id');
    }

    public function getProviderId(): int
    {
        return $this->getInt('provider_id');
    }

    /**
     * Backend customer identifier.
     */
    public function getRemoteUserId(): int
    {
        return $this->getInt('remote_user_id');
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
     * Encrypted bearer token as stored.
     */
    public function getEncryptedToken(): string
    {
        return $this->getString('token');
    }

    public function hasToken(): bool
    {
        return $this->getEncryptedToken() !== '';
    }

    /**
     * Whether the stored token is missing or expires within the grace period.
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
        return $this->getString('status', self::STATUS_ACTIVE);
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    public function getLastError(): string
    {
        return $this->getString('last_error');
    }
}
