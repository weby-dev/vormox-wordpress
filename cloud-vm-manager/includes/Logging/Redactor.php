<?php

/**
 * Secret redaction.
 *
 * @package CloudVmManager
 */

declare(strict_types=1);

namespace CloudVmManager\Logging;

use CloudVmManager\Support\Str;

defined('ABSPATH') || defined('WP_UNINSTALL_PLUGIN') || exit;

/**
 * Strips credentials out of log context before it is persisted.
 *
 * Passwords, tokens and one time codes are removed entirely; bearer tokens that
 * appear inside free text are masked. This runs on every log write, so a secret
 * cannot reach the log table even when a caller forgets to filter its context.
 */
final class Redactor
{
    private const REPLACEMENT = '[redacted]';

    /**
     * Context keys whose value is never stored.
     *
     * @var string[]
     */
    private const SENSITIVE_KEYS = [
        'password',
        'newpassword',
        'confirmpassword',
        'currentpassword',
        'rootpassword',
        'token',
        'apitoken',
        'api_token',
        'accesstoken',
        'access_token',
        'refreshtoken',
        'refresh_token',
        'authorization',
        'auth',
        'secret',
        'apikey',
        'api_key',
        'otp',
        'smtppassword',
        'smtp_password',
        'credentials',
    ];

    /**
     * Maximum characters kept per string value.
     */
    private const MAX_STRING_LENGTH = 2000;

    /**
     * Maximum depth traversed in nested payloads.
     */
    private const MAX_DEPTH = 8;

    /**
     * Redact a log context.
     *
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function redact(array $context): array
    {
        $redacted = $this->walk($context, 0);

        return is_array($redacted) ? $redacted : [];
    }

    /**
     * Mask bearer tokens inside a free text value.
     */
    public function redactString(string $value): string
    {
        $value = (string) preg_replace('/(Bearer\s+)[A-Za-z0-9\-\._~\+\/]+=*/i', '$1' . self::REPLACEMENT, $value);
        $value = (string) preg_replace(
            '/("(?:password|token|otp)"\s*:\s*")[^"]*(")/i',
            '$1' . self::REPLACEMENT . '$2',
            $value
        );

        return Str::truncate($value, self::MAX_STRING_LENGTH);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function walk($value, int $depth)
    {
        if ($depth > self::MAX_DEPTH) {
            return self::REPLACEMENT;
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                if (is_string($key) && $this->isSensitive($key)) {
                    $result[$key] = self::REPLACEMENT;

                    continue;
                }

                $result[$key] = $this->walk($item, $depth + 1);
            }

            return $result;
        }

        if (is_object($value)) {
            return $this->walk(get_object_vars($value), $depth + 1);
        }

        if (is_string($value)) {
            return $this->redactString($value);
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return self::REPLACEMENT;
    }

    private function isSensitive(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '', $key));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['password', 'token', 'secret', 'apikey'] as $needle) {
            if (strpos($normalized, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
