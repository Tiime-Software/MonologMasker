<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Masker;

/**
 * Recursively masks sensitive data in an associative array.
 */
interface MaskerInterface
{
    /**
     * Returns a masked copy of the given array. The input is never mutated.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public function mask(array $data): array;

    /**
     * Masks sensitive tokens inside a standalone string (e.g. a log message).
     */
    public function maskString(string $value): string;
}
