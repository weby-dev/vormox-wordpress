<?php

/**
 * JWT helper.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Support;

defined('ABSPATH') || exit;

/**
 * Reads the public claims of a bearer token.
 *
 * The backend does not document a token lifetime, so the expiry is taken from
 * the standard `exp` claim when the token is a JWT. Signatures are never
 * verified here: the token is only ever validated by the backend, this class
 * exists so the plugin can renew a token before it is rejected.
 */
final class Jwt
{
    /**
     * Not instantiable.
     */
    private function __construct()
    {
    }

    /**
     * Decoded payload claims of a token.
     *
     * @return array<string, mixed>
     */
    public static function claims(string $token): array
    {
        $segments = explode('.', $token);

        if (count($segments) !== 3) {
            return [];
        }

        $payload = self::base64UrlDecode($segments[1]);

        if ($payload === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Unix timestamp the token expires at, or null when it does not say.
     */
    public static function expiresAt(string $token): ?int
    {
        $claims = self::claims($token);

        if (!isset($claims['exp']) || !is_numeric($claims['exp'])) {
            return null;
        }

        $expiry = (int) $claims['exp'];

        return $expiry > 0 ? $expiry : null;
    }

    /**
     * Expiry formatted for storage, or an empty string when unknown.
     */
    public static function expiresAtForStorage(string $token): string
    {
        $expiry = self::expiresAt($token);

        return $expiry === null ? '' : gmdate('Y-m-d H:i:s', $expiry);
    }

    /**
     * Whether the token carries an expiry that already passed.
     */
    public static function isExpired(string $token, int $graceSeconds = 0): bool
    {
        $expiry = self::expiresAt($token);

        if ($expiry === null) {
            return false;
        }

        return $expiry - $graceSeconds <= time();
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;

        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return is_string($decoded) ? $decoded : '';
    }
}
