<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

use Tiime\MonologMasker\Config\DefaultValuePatterns;

/**
 * Matches a string value against a set of PCRE patterns. Patterns are validated
 * once at construction so a malformed pattern fails fast instead of at log time.
 */
final class RegexValueMatcher implements ValueMatcherInterface
{
    /**
     * @var list<string>
     */
    private readonly array $patterns;

    /**
     * @param array<string, string>|list<string> $patterns map of name => PCRE pattern (or a plain list)
     */
    public function __construct(array $patterns)
    {
        $validated = [];
        foreach ($patterns as $name => $pattern) {
            if (false === @preg_match($pattern, '')) {
                throw new \InvalidArgumentException(\sprintf('Invalid regular expression for pattern "%s": %s', $name, $pattern));
            }

            $validated[] = $pattern;
        }

        $this->patterns = $validated;
    }

    /**
     * Builds a matcher seeded with the curated default patterns.
     *
     * @param array<string, string> $additionalPatterns extra patterns merged with the defaults
     */
    public static function withDefaults(array $additionalPatterns = []): self
    {
        return new self([...DefaultValuePatterns::all(), ...$additionalPatterns]);
    }

    public function matches(string $value): bool
    {
        if ('' === $value) {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if (1 === preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}
