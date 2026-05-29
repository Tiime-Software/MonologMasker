<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Config;

/**
 * Curated PCRE patterns used to detect sensitive values regardless of their
 * key. Each entry is keyed by a human-readable name so callers can override or
 * drop individual patterns.
 *
 * These are intentionally conservative to limit false positives; they catch
 * the most common leak shapes rather than every theoretical format.
 */
final class DefaultValuePatterns
{
    public const PATTERNS = [
        // RFC-ish email address.
        'email' => '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i',

        // JSON Web Token (header.payload.signature).
        'jwt' => '/\beyJ[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\.[A-Za-z0-9_\-]+\b/',

        // "Bearer <token>" / "Basic <token>" authorization values.
        'bearer' => '/\b(?:Bearer|Basic)\s+[A-Za-z0-9._~+\/\-]+=*/i',

        // IBAN (2 letters + 2 check digits + up to 30 alphanumerics).
        'iban' => '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/',

        // Stripe-style secret/restricted keys.
        'api_secret_key' => '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}\b/',

        // AWS access key id.
        'aws_access_key' => '/\bAKIA[0-9A-Z]{16}\b/',

        // Google API key.
        'google_api_key' => '/\bAIza[0-9A-Za-z_\-]{35}\b/',

        // PEM-encoded private key block header.
        'pem_private_key' => '/-----BEGIN (?:[A-Z]+ )?PRIVATE KEY-----/',
    ];

    // Card numbers are detected separately (with a Luhn check) by
    // {@see \Tiime\MonologMasker\Matcher\CreditCardMatcher} to avoid the false
    // positives a plain digit-run regex would cause.

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::PATTERNS;
    }
}
