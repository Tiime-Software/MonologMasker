<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

/**
 * Decides whether a context/extra key is sensitive and must be masked.
 */
interface KeyMatcherInterface
{
    /**
     * @param array-key $key
     */
    public function matches(string|int $key): bool;
}
