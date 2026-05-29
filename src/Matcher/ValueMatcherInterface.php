<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Matcher;

/**
 * Decides whether a scalar string value is sensitive on its own (e.g. it looks
 * like an email, a credit card number or a JWT), regardless of its key.
 */
interface ValueMatcherInterface
{
    public function matches(string $value): bool;
}
