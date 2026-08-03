<?php

/**
 * Credential encryption.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Support;

use CloudVmManager\Contracts\EncryptorInterface;
use CloudVmManager\Exception\EncryptionException;
use Throwable;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Authenticated AES-256-CBC encryption for stored credentials.
 *
 * The envelope is `cvm1:base64(iv || hmac || ciphertext)`. The HMAC covers the
 * initialisation vector and the ciphertext and is verified in constant time
 * before decryption, so a tampered value is rejected instead of decrypted.
 *
 * Key material is taken from `CVM_ENCRYPTION_KEY` when defined, otherwise from
 * the WordPress salts, otherwise from a generated option. Two independent keys
 * are derived: one for the cipher and one for the signature.
 */
final class Encryptor implements EncryptorInterface
{
    private const CIPHER = 'aes-256-cbc';
    private const PREFIX = 'cvm1:';
    private const IV_LENGTH = 16;
    private const MAC_LENGTH = 32;
    private const FALLBACK_OPTION = 'cvm_encryption_material';

    /**
     * @var string
     */
    private $encryptionKey;

    /**
     * @var string
     */
    private $signingKey;

    public function __construct()
    {
        $material = $this->keyMaterial();

        $this->encryptionKey = hash_hmac('sha256', 'cloud-vm-manager/encryption', $material, true);
        $this->signingKey = hash_hmac('sha256', 'cloud-vm-manager/signature', $material, true);
    }

    public function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        try {
            $iv = random_bytes(self::IV_LENGTH);
        } catch (Throwable $exception) {
            throw new EncryptionException('Unable to generate an initialisation vector.', 0, $exception);
        }

        $ciphertext = openssl_encrypt($value, self::CIPHER, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new EncryptionException('Unable to encrypt the given value.');
        }

        $mac = hash_hmac('sha256', $iv . $ciphertext, $this->signingKey, true);

        return self::PREFIX . base64_encode($iv . $mac . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if ($payload === '') {
            return '';
        }

        if (!$this->isEncrypted($payload)) {
            throw new EncryptionException('The given payload is not a valid ciphertext envelope.');
        }

        $binary = base64_decode(substr($payload, strlen(self::PREFIX)), true);

        if ($binary === false || strlen($binary) <= self::IV_LENGTH + self::MAC_LENGTH) {
            throw new EncryptionException('The given ciphertext is malformed.');
        }

        $iv = substr($binary, 0, self::IV_LENGTH);
        $mac = substr($binary, self::IV_LENGTH, self::MAC_LENGTH);
        $ciphertext = substr($binary, self::IV_LENGTH + self::MAC_LENGTH);

        $expected = hash_hmac('sha256', $iv . $ciphertext, $this->signingKey, true);

        if (!hash_equals($expected, $mac)) {
            throw new EncryptionException('The ciphertext signature does not match.');
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->encryptionKey, OPENSSL_RAW_DATA, $iv);

        if ($plaintext === false) {
            throw new EncryptionException('Unable to decrypt the given ciphertext.');
        }

        return $plaintext;
    }

    public function decryptSafely(string $payload, string $fallback = ''): string
    {
        if ($payload === '') {
            return $fallback;
        }

        try {
            return $this->decrypt($payload);
        } catch (EncryptionException $exception) {
            return $fallback;
        }
    }

    public function isEncrypted(string $value): bool
    {
        return strncmp($value, self::PREFIX, strlen(self::PREFIX)) === 0;
    }

    /**
     * Secret material the two keys are derived from.
     */
    private function keyMaterial(): string
    {
        if (defined('CVM_ENCRYPTION_KEY')) {
            $configured = constant('CVM_ENCRYPTION_KEY');

            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        }

        $salts = '';

        foreach (['LOGGED_IN_KEY', 'LOGGED_IN_SALT', 'SECURE_AUTH_KEY', 'AUTH_SALT'] as $constant) {
            if (defined($constant) && is_string(constant($constant))) {
                $salts .= (string) constant($constant);
            }
        }

        if (strlen($salts) >= 32) {
            return $salts;
        }

        return $this->fallbackMaterial();
    }

    /**
     * Generated key material for installations without usable salts.
     */
    private function fallbackMaterial(): string
    {
        $stored = get_option(self::FALLBACK_OPTION, '');

        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $generated = bin2hex(random_bytes(32));

        update_option(self::FALLBACK_OPTION, $generated, 'no');

        return $generated;
    }
}
