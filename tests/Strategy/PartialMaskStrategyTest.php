<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Strategy;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Strategy\PartialMaskStrategy;

final class PartialMaskStrategyTest extends TestCase
{
    public function testKeepsLastVisibleCharacters(): void
    {
        $strategy = new PartialMaskStrategy(4, '*');

        self::assertSame('*********1234', $strategy->mask('secret-pw1234'));
    }

    public function testMasksEntirelyWhenValueShorterThanOrEqualToVisible(): void
    {
        $strategy = new PartialMaskStrategy(4, '*');

        self::assertSame('****', $strategy->mask('1234'));
        self::assertSame('**', $strategy->mask('ab'));
    }

    public function testMasksEmptyValueWithSingleMaskChar(): void
    {
        $strategy = new PartialMaskStrategy(4, '*');

        self::assertSame('*', $strategy->mask(''));
    }

    public function testHandlesMultibyteValues(): void
    {
        $strategy = new PartialMaskStrategy(2, '*');

        // 5 characters → 3 masked + last 2 kept.
        self::assertSame('***ée', $strategy->mask('àçxée'));
    }

    public function testRejectsZeroVisible(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PartialMaskStrategy(0);
    }

    public function testRejectsEmptyMaskChar(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PartialMaskStrategy(4, '');
    }

    public function testUsesBlockCharAndFourVisibleByDefault(): void
    {
        $strategy = new PartialMaskStrategy();

        // 11 chars → 7 masked with the default '█' + last 4 kept.
        self::assertSame('███████1234', $strategy->mask('abcdefg1234'));
    }

    public function testRevealsTailAtExactBoundary(): void
    {
        $strategy = new PartialMaskStrategy(4, '*');

        // length 5, visible 4 → exactly one masked char.
        self::assertSame('*bcde', $strategy->mask('abcde'));
    }

    public function testMasksEntirelyWhenVisibleExceedsLength(): void
    {
        $strategy = new PartialMaskStrategy(10, '*');

        self::assertSame('****', $strategy->mask('abcd'));
    }

    public function testSupportsMultibyteMaskCharacter(): void
    {
        $strategy = new PartialMaskStrategy(2, '●');

        self::assertSame('●●●cd', $strategy->mask('abccd'));
    }

    public function testAllowsExactlyOneVisibleCharacter(): void
    {
        // visible == 1 is the lower bound and must be accepted, not rejected.
        $strategy = new PartialMaskStrategy(1, '*');

        self::assertSame('***d', $strategy->mask('abcd'));
    }
}
