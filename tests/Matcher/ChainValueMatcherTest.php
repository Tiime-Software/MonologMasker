<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Matcher\ChainValueMatcher;
use Tiime\MonologMasker\Matcher\CreditCardMatcher;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;

final class ChainValueMatcherTest extends TestCase
{
    private function chain(): ChainValueMatcher
    {
        return new ChainValueMatcher(
            new RegexValueMatcher(['/\baaa\b/']),
            new CreditCardMatcher(),
        );
    }

    public function testMatchesIfAnyChildMatches(): void
    {
        $chain = $this->chain();

        self::assertTrue($chain->matches('aaa'));
        self::assertTrue($chain->matches('4242424242424242'));
        self::assertFalse($chain->matches('zzz'));
    }

    public function testRedactsWithEveryChildInTurn(): void
    {
        $masked = $this->chain()->redact('aaa and 4242424242424242', new FullMaskStrategy('***'));

        self::assertSame('*** and ***', $masked);
    }

    public function testEmptyChainMatchesNothingAndRedactsNothing(): void
    {
        $chain = new ChainValueMatcher();

        self::assertFalse($chain->matches('aaa'));
        self::assertSame('aaa', $chain->redact('aaa', new FullMaskStrategy('***')));
    }
}
