<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Property;

use Innmind\BlackBox\PHPUnit\BlackBox;
use Innmind\BlackBox\Set;
use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;
use Tiime\MonologMasker\Strategy\PartialMaskStrategy;

final class StrategyPropertyTest extends TestCase
{
    use BlackBox;

    /**
     * Mixed ASCII + multibyte characters (no `intl` extension required).
     */
    private static function text(int $min, int $max): Set
    {
        return Set::strings()
            ->madeOf(Set::of('a', 'Z', '1', '9', ' ', 'é', 'ç', 'ü', '€', '日'))
            ->between($min, $max);
    }

    public function testPartialMaskPreservesLengthAndRevealsOnlyTheTail(): void
    {
        $this
            ->forAll(
                self::text(1, 30),
                Set::integers()->between(1, 10),
            )
            ->then(function (string $value, int $visible): void {
                $masked = (new PartialMaskStrategy($visible, '*'))->mask($value);
                $length = mb_strlen($value);

                // Length is always preserved for a non-empty value.
                $this->assertSame($length, mb_strlen($masked));

                if ($length > $visible) {
                    // The last $visible characters are revealed, the rest masked.
                    $this->assertSame(mb_substr($value, -$visible), mb_substr($masked, -$visible));
                    $this->assertSame(
                        str_repeat('*', $length - $visible),
                        mb_substr($masked, 0, $length - $visible),
                    );
                } else {
                    // Short values are masked entirely.
                    $this->assertSame(str_repeat('*', $length), $masked);
                }
            });
    }

    public function testFullMaskIsConstantRegardlessOfInput(): void
    {
        $this
            ->forAll(self::text(0, 30))
            ->then(function (string $value): void {
                $this->assertSame('§', (new FullMaskStrategy('§'))->mask($value));
            });
    }
}
