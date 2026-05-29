<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Masker;

use Tiime\MonologMasker\Matcher\KeyMatcherInterface;
use Tiime\MonologMasker\Matcher\ValueMatcherInterface;
use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Framework-agnostic masking engine. It walks an array recursively and masks:
 *
 *  - any value whose key is flagged sensitive by the {@see KeyMatcherInterface}
 *    (the whole sub-tree is replaced, so nested secrets cannot leak);
 *  - any leaf string flagged sensitive by the optional {@see ValueMatcherInterface}.
 *
 * The input array is never mutated — {@see mask()} returns a fresh copy.
 *
 * Recursion is bounded by a configurable maximum depth; branches deeper than
 * that are replaced with a truncation marker rather than traversed, which also
 * protects against self-referential arrays.
 */
final class Masker implements MaskerInterface
{
    public const TRUNCATED = '[TRUNCATED]';

    public function __construct(
        private readonly KeyMatcherInterface $keyMatcher,
        private readonly ?ValueMatcherInterface $valueMatcher,
        private readonly MaskStrategyInterface $strategy,
        private readonly int $maxDepth = 16,
    ) {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('The maximum depth must be at least 1.');
        }
    }

    public function mask(array $data): array
    {
        return $this->process($data, 1);
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function process(array $data, int $depth): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            if ($this->keyMatcher->matches($key)) {
                $masked[$key] = $this->maskValue($value);

                continue;
            }

            if (\is_array($value)) {
                $masked[$key] = $depth >= $this->maxDepth
                    ? self::TRUNCATED
                    : $this->process($value, $depth + 1);

                continue;
            }

            $masked[$key] = $this->maskLeaf($value);
        }

        return $masked;
    }

    /**
     * Masks a leaf (non-array) value when it is a string flagged by the value
     * matcher; otherwise returns it untouched.
     */
    private function maskLeaf(mixed $value): mixed
    {
        if (null === $this->valueMatcher) {
            return $value;
        }

        if (\is_string($value) && $this->valueMatcher->matches($value)) {
            return $this->strategy->mask($value);
        }

        return $value;
    }

    /**
     * Produces the masked representation of a key-matched value, regardless of
     * its type. Arrays and objects are collapsed entirely so no nested secret
     * survives under a sensitive key.
     */
    private function maskValue(mixed $value): string
    {
        if (\is_string($value)) {
            return $this->strategy->mask($value);
        }

        if (\is_bool($value)) {
            return $this->strategy->mask($value ? 'true' : 'false');
        }

        if (\is_int($value) || \is_float($value)) {
            return $this->strategy->mask((string) $value);
        }

        if ($value instanceof \Stringable) {
            return $this->strategy->mask((string) $value);
        }

        return $this->strategy->mask('');
    }
}
