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

        // 13-to-16 digit card numbers, optionally grouped by spaces or dashes.
        'credit_card' => '/\b(?:\d[ \-]?){12,18}\d\b/',

        // IBAN (2 letters + 2 check digits + up to 30 alphanumerics).
        'iban' => '/\b[A-Z]{2}\d{2}[A-Z0-9]{11,30}\b/',

        // Stripe-style secret/restricted keys.
        'api_secret_key' => '/\b(?:sk|rk)_(?:live|test)_[A-Za-z0-9]{16,}\b/',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::PATTERNS;
    }
}
