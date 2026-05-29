<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Strategy;

/**
 * Replaces the whole value with a fixed placeholder. Zero leakage — the
 * recommended default.
 */
final class FullMaskStrategy implements MaskStrategyInterface
{
    public const DEFAULT_PLACEHOLDER = '████████';

    public function __construct(
        private readonly string $placeholder = self::DEFAULT_PLACEHOLDER,
    ) {
    }

    public function mask(string $value): string
    {
        return $this->placeholder;
    }
}
