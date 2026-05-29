<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Config;

/**
 * Curated list of key names commonly carrying secrets or PII. Matching is
 * case-insensitive (see {@see \Tiime\MonologMasker\Matcher\KeyListMatcher}).
 */
final class DefaultSensitiveKeys
{
    public const KEYS = [
        'password',
        'passwd',
        'pwd',
        'secret',
        'api_key',
        'apikey',
        'token',
        'access_token',
        'refresh_token',
        'id_token',
        'authorization',
        'auth',
        'client_secret',
        'private_key',
        'secret_key',
        'credit_card',
        'card_number',
        'cvv',
        'cvc',
        'pin',
        'otp',
        'cookie',
        'set-cookie',
        'x-api-key',
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::KEYS;
    }
}
