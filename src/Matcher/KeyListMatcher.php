<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

use Tiime\MonologMasker\Config\DefaultSensitiveKeys;

/**
 * Matches keys against a fixed, case-insensitive list of sensitive key names.
 *
 * Keys are normalised to lower-case once at construction time and stored as a
 * hash set, so {@see matches()} is O(1).
 */
final class KeyListMatcher implements KeyMatcherInterface
{
    /**
     * @var array<string, true>
     */
    private readonly array $keys;

    /**
     * @param list<string> $keys
     */
    public function __construct(array $keys)
    {
        $set = [];
        foreach ($keys as $key) {
            $set[mb_strtolower($key)] = true;
        }

        $this->keys = $set;
    }

    /**
     * Builds a matcher seeded with the curated default key list.
     *
     * @param list<string> $additionalKeys extra keys merged with the defaults
     */
    public static function withDefaults(array $additionalKeys = []): self
    {
        return new self([...DefaultSensitiveKeys::all(), ...$additionalKeys]);
    }

    public function matches(string|int $key): bool
    {
        if (\is_int($key)) {
            return false;
        }

        return $this->keys[mb_strtolower($key)] ?? false;
    }
}
