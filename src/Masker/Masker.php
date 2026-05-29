<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Masker;

use Tiime\MonologMasker\Matcher\KeyMatcherInterface;
use Tiime\MonologMasker\Matcher\ValueMatcherInterface;
use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Framework-agnostic masking engine. It walks a structure recursively and masks:
 *
 *  - any value whose key is flagged sensitive by the {@see KeyMatcherInterface}
 *    (the whole sub-tree is replaced, so nested secrets cannot leak);
 *  - any sensitive token inside a leaf string/number flagged by the optional
 *    {@see ValueMatcherInterface} (sub-string masking, the rest is preserved).
 *
 * Objects are traversed too (unless disabled): {@see \JsonSerializable} via its
 * serialized form, {@see \Stringable} via its string form, and plain objects via
 * their public properties — matching how Monolog would otherwise serialise them,
 * so secrets they carry cannot slip through unmasked.
 *
 * The input is never mutated — {@see mask()} returns a fresh copy. Recursion is
 * bounded by a configurable maximum depth (branches beyond it become a
 * truncation marker), and object cycles are tracked to avoid infinite loops.
 */
final class Masker implements MaskerInterface
{
    public const TRUNCATED = '[TRUNCATED]';

    public function __construct(
        private readonly KeyMatcherInterface $keyMatcher,
        private readonly ?ValueMatcherInterface $valueMatcher,
        private readonly MaskStrategyInterface $strategy,
        private readonly int $maxDepth = 16,
        private readonly bool $traverseObjects = true,
    ) {
        if ($maxDepth < 1) {
            throw new \InvalidArgumentException('The maximum depth must be at least 1.');
        }
    }

    public function mask(array $data): array
    {
        return $this->processArray($data, 1, new \SplObjectStorage());
    }

    /**
     * Masks every sensitive token inside a standalone string (e.g. a log
     * message). Returns it unchanged when value matching is disabled.
     */
    public function maskString(string $value): string
    {
        return $this->valueMatcher?->redact($value, $this->strategy) ?? $value;
    }

    /**
     * @param array<array-key, mixed>          $data
     * @param \SplObjectStorage<object, mixed> $seen
     *
     * @return array<array-key, mixed>
     */
    private function processArray(array $data, int $depth, \SplObjectStorage $seen): array
    {
        $masked = [];

        foreach ($data as $key => $value) {
            $masked[$key] = $this->keyMatcher->matches($key)
                ? $this->maskValue($value)
                : $this->processValue($value, $depth, $seen);
        }

        return $masked;
    }

    /**
     * @param \SplObjectStorage<object, mixed> $seen
     */
    private function processValue(mixed $value, int $depth, \SplObjectStorage $seen): mixed
    {
        if (\is_array($value)) {
            return $depth >= $this->maxDepth
                ? self::TRUNCATED
                : $this->processArray($value, $depth + 1, $seen);
        }

        if (\is_object($value)) {
            return $this->processObject($value, $depth, $seen);
        }

        return $this->maskLeaf($value);
    }

    /**
     * @param \SplObjectStorage<object, mixed> $seen
     */
    private function processObject(object $value, int $depth, \SplObjectStorage $seen): mixed
    {
        if (!$this->traverseObjects) {
            return $value;
        }

        if ($value instanceof \JsonSerializable) {
            if ($seen->contains($value)) {
                return self::TRUNCATED;
            }

            $seen->attach($value);
            $masked = $this->processValue($value->jsonSerialize(), $depth, $seen);
            $seen->detach($value);

            return $masked;
        }

        if ($value instanceof \Stringable) {
            return $this->maskLeaf((string) $value);
        }

        if ($seen->contains($value)) {
            return self::TRUNCATED;
        }

        if ($depth >= $this->maxDepth) {
            return self::TRUNCATED;
        }

        $seen->attach($value);
        $masked = $this->processArray(get_object_vars($value), $depth + 1, $seen);
        $seen->detach($value);

        return $masked;
    }

    /**
     * Masks sensitive tokens inside a leaf: sub-string masking for strings, and
     * whole-value masking for an integer the matcher recognises. Other values
     * are returned untouched.
     */
    private function maskLeaf(mixed $value): mixed
    {
        if (null === $this->valueMatcher) {
            return $value;
        }

        if (\is_string($value)) {
            return $this->valueMatcher->redact($value, $this->strategy);
        }

        if (\is_int($value)) {
            $string = (string) $value;

            return $this->valueMatcher->matches($string)
                ? $this->strategy->mask($string)
                : $value;
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
