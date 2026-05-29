<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Matcher\CreditCardMatcher;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;

final class CreditCardMatcherTest extends TestCase
{
    private CreditCardMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new CreditCardMatcher();
    }

    /**
     * @dataProvider validCards
     */
    public function testMatchesLuhnValidCardNumbers(string $value): void
    {
        self::assertTrue($this->matcher->matches($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validCards(): iterable
    {
        yield 'visa' => ['4242424242424242'];
        yield 'visa grouped (spaces)' => ['4242 4242 4242 4242'];
        yield 'visa grouped (dashes)' => ['4242-4242-4242-4242'];
        yield 'amex (15)' => ['378282246310005'];
        yield 'mastercard' => ['5555555555554444'];
    }

    /**
     * @dataProvider nonCards
     */
    public function testDoesNotMatchNonCards(string $value): void
    {
        self::assertFalse($this->matcher->matches($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonCards(): iterable
    {
        yield 'wrong checksum' => ['4242424242424241'];
        yield 'too short' => ['123456789012'];
        yield 'too long' => ['12345678901234567890'];
        yield 'not digits' => ['hello world'];
        yield 'empty' => [''];
    }

    public function testRedactsOnlyTheCardSubString(): void
    {
        $masked = $this->matcher->redact('pay with 4242424242424242 now', new FullMaskStrategy('***'));

        self::assertSame('pay with *** now', $masked);
    }

    public function testRedactLeavesNonLuhnDigitsUntouched(): void
    {
        $value = 'order 4242424242424241 placed';

        self::assertSame($value, $this->matcher->redact($value, new FullMaskStrategy('***')));
    }

    public function testRedactReturnsEmptyStringUnchanged(): void
    {
        self::assertSame('', $this->matcher->redact('', new FullMaskStrategy('***')));
    }
}
