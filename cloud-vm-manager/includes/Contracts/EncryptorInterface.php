<?php

/**
 * Encryption contract.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Contracts;

use CloudVmManager\Exception\EncryptionException;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Symmetric encryption used for provider credentials and customer tokens.
 */
interface EncryptorInterface
{
    /**
     * @throws EncryptionException When the value cannot be encrypted.
     */
    public function encrypt(string $value): string;

    /**
     * @throws EncryptionException When the payload is malformed or was tampered with.
     */
    public function decrypt(string $payload): string;

    /**
     * Decrypt without throwing, returning the fallback on failure.
     */
    public function decryptSafely(string $payload, string $fallback = ''): string;

    /**
     * Whether the given value carries the plugin ciphertext envelope.
     */
    public function isEncrypted(string $value): bool;
}
