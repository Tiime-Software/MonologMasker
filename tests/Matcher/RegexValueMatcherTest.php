<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;

final class RegexValueMatcherTest extends TestCase
{
    /**
     * @dataProvider sensitiveValues
     */
    public function testMatchesDefaultSensitiveValues(string $value): void
    {
        self::assertTrue(RegexValueMatcher::withDefaults()->matches($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveValues(): iterable
    {
        yield 'email' => ['john.doe@example.com'];
        yield 'jwt' => ['eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.s5pVl0lK3a8xZ_q2cQ8m1Q'];
        yield 'bearer' => ['Bearer abc123.def-456_ghi'];
        yield 'credit card' => ['4242 4242 4242 4242'];
        yield 'iban' => ['FR7630006000011234567890189'];
        yield 'stripe key' => ['sk_live_abcdef0123456789ABCDEF'];
    }

    /**
     * @dataProvider harmlessValues
     */
    public function testDoesNotMatchHarmlessValues(string $value): void
    {
        self::assertFalse(RegexValueMatcher::withDefaults()->matches($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function harmlessValues(): iterable
    {
        yield 'plain word' => ['hello'];
        yield 'small number' => ['42'];
        yield 'empty' => [''];
        // Near-misses for each default pattern — must NOT trigger.
        yield 'email without tld' => ['john.doe@localhost'];
        yield 'jwt with two segments' => ['eyJhbGci.payloadonly'];
        yield 'too few digits for card' => ['123456789012'];
        yield 'iban too short' => ['FR76'];
    }

    public function testRejectsInvalidPattern(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RegexValueMatcher(['broken' => '/(unclosed']);
    }

    public function testAcceptsAdditionalPatterns(): void
    {
        $matcher = RegexValueMatcher::withDefaults(['fr_phone' => '/\b0[1-9](?:\d{2}){4}\b/']);

        self::assertTrue($matcher->matches('0612345678'));
    }

    public function testAcceptsListFormOfPatterns(): void
    {
        // Non-associative (plain list) construction is supported too.
        $matcher = new RegexValueMatcher(['/\bfoo\b/']);

        self::assertTrue($matcher->matches('a foo b'));
        self::assertFalse($matcher->matches('a bar b'));
    }

    public function testEmptyPatternSetMatchesNothing(): void
    {
        $matcher = new RegexValueMatcher([]);

        self::assertFalse($matcher->matches('john.doe@example.com'));
    }
}
