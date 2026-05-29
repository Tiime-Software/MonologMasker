<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Property;

use Innmind\BlackBox\PHPUnit\BlackBox;
use Innmind\BlackBox\Set;
use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;

final class MatcherPropertyTest extends TestCase
{
    use BlackBox;

    public function testSensitiveKeysMatchRegardlessOfCase(): void
    {
        $matcher = KeyListMatcher::withDefaults();

        $this
            ->forAll(Generators::sensitiveKeys())
            ->then(function (string $key) use ($matcher): void {
                $this->assertTrue($matcher->matches($key));
                $this->assertTrue($matcher->matches(strtoupper($key)));
                $this->assertTrue($matcher->matches(ucfirst($key)));
                $this->assertTrue($matcher->matches(mb_strtoupper($key)));
            });
    }

    public function testCuratedSensitiveValuesAlwaysMatch(): void
    {
        $matcher = RegexValueMatcher::withDefaults();

        $this
            ->forAll(Set::of(
                'john.doe@example.com',
                'a.b+tag@sub.domain.co',
                '4242424242424242',
                '378282246310005',
                'FR7630006000011234567890189',
                'GB29NWBK60161331926819',
                'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.abc123',
                'Bearer abc.def-123',
                'Basic dXNlcjpwYXNz',
            ))
            ->then(function (string $value) use ($matcher): void {
                $this->assertTrue($matcher->matches($value));
            });
    }

    public function testLowerCaseAsciiStringsNeverMatch(): void
    {
        $matcher = RegexValueMatcher::withDefaults();

        $this
            ->forAll(Set::strings()->madeOf(Set::of(...range('a', 'z')))->between(1, 12))
            ->then(function (string $value) use ($matcher): void {
                $this->assertFalse($matcher->matches($value));
            });
    }
}
