<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

use Tiime\MonologMasker\Config\DefaultSensitiveKeys;

/**
 * Matches keys by *segments* instead of whole-string equality, so compound and
 * nested names are caught: `db_password`, `userToken`, `x-api-key`, `apiKey`….
 *
 * A key is split into lower-cased segments on separators (`_ - . / space`) and
 * camelCase boundaries. A sensitive entry matches when its own segments appear
 * as a consecutive run inside the key's segments. Single-word entries therefore
 * match any segment, while multi-word entries (e.g. `api_key`) require the
 * sequence — which avoids false positives such as `tokenizer` matching `token`.
 */
final class SegmentKeyMatcher implements KeyMatcherInterface
{
    /**
     * @var list<list<string>>
     */
    private readonly array $needles;

    /**
     * @param list<string> $keys
     */
    public function __construct(array $keys)
    {
        $needles = [];
        foreach ($keys as $key) {
            $segments = self::segments($key);
            if ([] !== $segments) {
                $needles[] = $segments;
            }
        }

        $this->needles = $needles;
    }

    /**
     * @param list<string> $additionalKeys
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

        $haystack = self::segments($key);
        if ([] === $haystack) {
            return false;
        }

        foreach ($this->needles as $needle) {
            if (self::containsSequence($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function segments(string $key): array
    {
        // Insert a space at camelCase boundaries (fooBar, XMLToken).
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $key) ?? $key;
        $spaced = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $spaced) ?? $spaced;

        $parts = preg_split('/[\s_\-.\/]+/', $spaced) ?: [];

        $segments = [];
        foreach ($parts as $part) {
            if ('' !== $part) {
                $segments[] = mb_strtolower($part);
            }
        }

        return $segments;
    }

    /**
     * Whether $needle appears as a consecutive run inside $haystack. Both lists
     * are joined with a delimiter so a plain substring search enforces the
     * "whole segment, in order" rule (e.g. `token` matches `user/token` but not
     * `tokenizer`).
     *
     * @param list<string> $haystack
     * @param list<string> $needle
     */
    private static function containsSequence(array $haystack, array $needle): bool
    {
        return str_contains(
            '/'.implode('/', $haystack).'/',
            '/'.implode('/', $needle).'/',
        );
    }
}
