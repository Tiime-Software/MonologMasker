<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

/**
 * Decides whether a scalar string value is sensitive on its own (e.g. it looks
 * like an email, a credit card number or a JWT), regardless of its key.
 */
interface ValueMatcherInterface
{
    /**
     * Whether the value contains at least one sensitive token.
     */
    public function matches(string $value): bool;

    /**
     * Returns the value with every sensitive token replaced by its masked
     * form (sub-string masking). A value with no sensitive token is returned
     * unchanged.
     */
    public function redact(string $value, MaskStrategyInterface $strategy): string;
}
