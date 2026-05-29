<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Matcher;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Matcher\KeyListMatcher;

final class KeyListMatcherTest extends TestCase
{
    public function testMatchesIsCaseInsensitive(): void
    {
        $matcher = new KeyListMatcher(['password', 'api_key']);

        self::assertTrue($matcher->matches('password'));
        self::assertTrue($matcher->matches('PASSWORD'));
        self::assertTrue($matcher->matches('Api_Key'));
    }

    public function testDoesNotMatchUnknownKey(): void
    {
        $matcher = new KeyListMatcher(['password']);

        self::assertFalse($matcher->matches('username'));
    }

    public function testNeverMatchesIntegerKeys(): void
    {
        $matcher = new KeyListMatcher(['password']);

        self::assertFalse($matcher->matches(0));
        self::assertFalse($matcher->matches(42));
    }

    public function testWithDefaultsIncludesCuratedKeysAndExtras(): void
    {
        $matcher = KeyListMatcher::withDefaults(['x-internal-token']);

        self::assertTrue($matcher->matches('token'));
        self::assertTrue($matcher->matches('authorization'));
        self::assertTrue($matcher->matches('X-Internal-Token'));
    }

    public function testWithDefaultsWithoutExtrasStillMatchesDefaults(): void
    {
        $matcher = KeyListMatcher::withDefaults();

        self::assertTrue($matcher->matches('password'));
        self::assertFalse($matcher->matches('not-a-default-key'));
    }

    public function testEmptyListNeverMatches(): void
    {
        $matcher = new KeyListMatcher([]);

        self::assertFalse($matcher->matches('password'));
    }

    public function testHandlesDuplicateKeys(): void
    {
        $matcher = new KeyListMatcher(['password', 'PASSWORD', 'password']);

        self::assertTrue($matcher->matches('password'));
    }

    public function testMatchesAccentedKeysCaseInsensitively(): void
    {
        // Requires multibyte-aware lowercasing on both sides (construction and
        // matching): plain strtolower would leave "É" untouched.
        $matcher = new KeyListMatcher(['CAFÉ']);

        self::assertTrue($matcher->matches('café'));
        self::assertTrue($matcher->matches('CAFÉ'));
    }
}
