<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Strategy;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;

final class FullMaskStrategyTest extends TestCase
{
    public function testReturnsDefaultPlaceholderRegardlessOfInput(): void
    {
        $strategy = new FullMaskStrategy();

        self::assertSame(FullMaskStrategy::DEFAULT_PLACEHOLDER, $strategy->mask('hunter2'));
        self::assertSame(FullMaskStrategy::DEFAULT_PLACEHOLDER, $strategy->mask(''));
    }

    public function testReturnsCustomPlaceholder(): void
    {
        $strategy = new FullMaskStrategy('***');

        self::assertSame('***', $strategy->mask('anything'));
    }

    public function testAcceptsEmptyPlaceholder(): void
    {
        $strategy = new FullMaskStrategy('');

        self::assertSame('', $strategy->mask('secret'));
    }

    public function testDefaultPlaceholderIsBlockCharacters(): void
    {
        self::assertSame('████████', (new FullMaskStrategy())->mask('secret'));
    }
}
