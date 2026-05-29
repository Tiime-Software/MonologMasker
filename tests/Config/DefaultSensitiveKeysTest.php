<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Config;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Config\DefaultSensitiveKeys;

final class DefaultSensitiveKeysTest extends TestCase
{
    public function testReturnsANonEmptyList(): void
    {
        self::assertNotEmpty(DefaultSensitiveKeys::all());
    }

    public function testContainsNoDuplicates(): void
    {
        $keys = DefaultSensitiveKeys::all();

        self::assertSame(array_values(array_unique($keys)), $keys);
    }

    public function testAllKeysAreLowerCase(): void
    {
        foreach (DefaultSensitiveKeys::all() as $key) {
            self::assertSame(strtolower($key), $key, \sprintf('Key "%s" should be lower-case.', $key));
        }
    }
}
