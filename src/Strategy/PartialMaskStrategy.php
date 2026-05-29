<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Strategy;

/**
 * Keeps the last N characters visible and masks the rest (e.g. "███1234").
 *
 * Useful for debugging where a tail is enough to correlate values. When the
 * value is shorter than (or equal to) the number of visible characters, it is
 * masked entirely so a short secret is never revealed in full.
 */
final class PartialMaskStrategy implements MaskStrategyInterface
{
    public function __construct(
        private readonly int $visible = 4,
        private readonly string $maskChar = '█',
    ) {
        if ($visible < 1) {
            throw new \InvalidArgumentException('The number of visible characters must be at least 1.');
        }

        if ('' === $maskChar) {
            throw new \InvalidArgumentException('The mask character must not be empty.');
        }
    }

    public function mask(string $value): string
    {
        $length = mb_strlen($value);

        if ($length <= $this->visible) {
            return str_repeat($this->maskChar, max($length, 1));
        }

        return str_repeat($this->maskChar, $length - $this->visible).mb_substr($value, -$this->visible);
    }
}
