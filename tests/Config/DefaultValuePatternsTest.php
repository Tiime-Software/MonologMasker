<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Config;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Config\DefaultValuePatterns;

final class DefaultValuePatternsTest extends TestCase
{
    public function testReturnsANonEmptyMap(): void
    {
        self::assertNotEmpty(DefaultValuePatterns::all());
    }

    /**
     * Guards against a broken regex being introduced into the default list:
     * every pattern must compile.
     *
     * @dataProvider patterns
     */
    public function testEveryDefaultPatternCompiles(string $name, string $pattern): void
    {
        self::assertNotFalse(
            @preg_match($pattern, ''),
            \sprintf('Default pattern "%s" is not a valid PCRE expression.', $name),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function patterns(): iterable
    {
        foreach (DefaultValuePatterns::all() as $name => $pattern) {
            yield $name => [$name, $pattern];
        }
    }
}
