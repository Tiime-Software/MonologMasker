<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Strategy;

/**
 * Decides how a sensitive value is replaced once it has been detected.
 */
interface MaskStrategyInterface
{
    /**
     * Returns the masked representation of the given sensitive string value.
     */
    public function mask(string $value): string;
}
