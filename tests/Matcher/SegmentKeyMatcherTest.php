<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Matcher\SegmentKeyMatcher;

final class SegmentKeyMatcherTest extends TestCase
{
    /**
     * @dataProvider sensitiveKeys
     */
    public function testMatchesCompoundAndNestedKeys(string $key): void
    {
        self::assertTrue(SegmentKeyMatcher::withDefaults()->matches($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function sensitiveKeys(): iterable
    {
        yield 'exact' => ['password'];
        yield 'snake prefix' => ['db_password'];
        yield 'camelCase' => ['userToken'];
        yield 'kebab multi-word' => ['x-api-key'];
        yield 'camel multi-word' => ['apiKey'];
        yield 'dotted' => ['request.authorization'];
        yield 'upper' => ['DB_PASSWORD'];
        yield 'XML camel boundary' => ['XMLAuthToken'];
    }

    /**
     * @dataProvider safeKeys
     */
    public function testDoesNotMatchSafeKeys(string $key): void
    {
        self::assertFalse(SegmentKeyMatcher::withDefaults()->matches($key));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function safeKeys(): iterable
    {
        // A sensitive word must appear as a whole segment, not a substring.
        yield 'tokenizer' => ['tokenizer'];
        yield 'author' => ['author'];
        yield 'username' => ['username'];
        yield 'passwordless is still a single segment' => ['passwordless'];
        // A sensitive word as a suffix of a larger segment must not match.
        yield 'mytoken' => ['mytoken'];
    }

    public function testNeverMatchesIntegerKeys(): void
    {
        self::assertFalse(SegmentKeyMatcher::withDefaults()->matches(0));
    }

    public function testMultiWordEntryRequiresTheSequence(): void
    {
        $matcher = new SegmentKeyMatcher(['api_key']);

        self::assertTrue($matcher->matches('api_key'));
        self::assertTrue($matcher->matches('publicApiKey'));
        // 'key' alone is not the configured sequence.
        self::assertFalse($matcher->matches('key'));
        self::assertFalse($matcher->matches('api'));
    }

    public function testEmptyAndSeparatorOnlyKeysNeverMatch(): void
    {
        $matcher = SegmentKeyMatcher::withDefaults();

        self::assertFalse($matcher->matches(''));
        self::assertFalse($matcher->matches('___'));
    }

    public function testIgnoresEmptyConfiguredKeys(): void
    {
        $matcher = new SegmentKeyMatcher(['']);

        self::assertFalse($matcher->matches('password'));
    }

    public function testSplitsUppercaseRunFollowedByLowercase(): void
    {
        // "APIToken" must split as API / Token (the XMLToken-style boundary).
        self::assertTrue(SegmentKeyMatcher::withDefaults()->matches('APIToken'));
    }

    public function testMatchesAccentedConfiguredKeysCaseInsensitively(): void
    {
        $matcher = new SegmentKeyMatcher(['café']);

        self::assertTrue($matcher->matches('CAFÉ'));
    }
}
