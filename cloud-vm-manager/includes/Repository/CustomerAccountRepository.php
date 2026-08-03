<?php

/**
 * Customer account repository.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Repository;

use CloudVmManager\Database\TableRegistry;
use CloudVmManager\Model\CustomerAccount;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Persistence for the WordPress user to backend account mapping.
 */
final class CustomerAccountRepository extends AbstractRepository
{
    protected function tableKey(): string
    {
        return TableRegistry::CUSTOMER_ACCOUNTS;
    }

    protected function modelClass(): string
    {
        return CustomerAccount::class;
    }

    /**
     * @return array<string, string>
     */
    protected function columns(): array
    {
        return [
            'id' => '%d',
            'user_id' => '%d',
            'provider_id' => '%d',
            'remote_user_id' => '%d',
            'email' => '%s',
            'password' => '%s',
            'token' => '%s',
            'token_expires_at' => '%s',
            'status' => '%s',
            'last_login_at' => '%s',
            'last_error' => '%s',
            'created_at' => '%s',
            'updated_at' => '%s',
        ];
    }

    /**
     * Account of a customer at a provider.
     */
    public function findForUser(int $userId, int $providerId): ?CustomerAccount
    {
        $account = $this->findOneBy(['user_id' => $userId, 'provider_id' => $providerId]);

        return $account instanceof CustomerAccount ? $account : null;
    }

    /**
     * Every provider account of a customer.
     *
     * @return CustomerAccount[]
     */
    public function forUser(int $userId): array
    {
        /** @var CustomerAccount[] $accounts */
        $accounts = $this->findBy(['user_id' => $userId], ['order_by' => 'provider_id', 'order' => 'ASC']);

        return $accounts;
    }

    /**
     * Store a freshly issued bearer token.
     *
     * @param string $encryptedToken Ciphertext produced by the encryptor.
     * @param string $expiresAt      UTC expiry in MySQL format, empty when unknown.
     */
    public function storeToken(int $accountId, string $encryptedToken, string $expiresAt = ''): void
    {
        $this->update(
            $accountId,
            [
                'token' => $encryptedToken,
                'token_expires_at' => $expiresAt !== '' ? $expiresAt : null,
                'status' => CustomerAccount::STATUS_ACTIVE,
                'last_login_at' => $this->now(),
                'last_error' => '',
            ]
        );
    }

    /**
     * Drop the stored token so the next request authenticates again.
     */
    public function clearToken(int $accountId): void
    {
        $this->update(
            $accountId,
            [
                'token' => '',
                'token_expires_at' => null,
            ]
        );
    }

    /**
     * Record an authentication failure.
     */
    public function markError(int $accountId, string $error): void
    {
        $this->update(
            $accountId,
            [
                'status' => CustomerAccount::STATUS_ERROR,
                'last_error' => $error,
            ]
        );
    }
}
