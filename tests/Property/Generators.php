<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Property;

use Innmind\BlackBox\Set;
use Tiime\MonologMasker\Config\DefaultSensitiveKeys;
use Tiime\MonologMasker\Matcher\ChainValueMatcher;
use Tiime\MonologMasker\Matcher\CreditCardMatcher;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Matcher\ValueMatcherInterface;

/**
 * black-box {@see Set} generators shared by the property tests.
 *
 * The generators reuse the library's own sources of truth
 * ({@see DefaultSensitiveKeys}, {@see RegexValueMatcher}) so the data they
 * produce stays in sync with what the engine actually treats as sensitive.
 */
final class Generators
{
    /**
     * Keys that are NOT in the default sensitive list.
     */
    public static function safeKeys(): Set
    {
        return Set::of('user', 'count', 'level', 'id', 'message', 'status', 'label', 'meta', 'data', 'name');
    }

    public static function sensitiveKeys(): Set
    {
        return Set::of(...DefaultSensitiveKeys::all());
    }

    public static function anyKey(): Set
    {
        return Set::either(self::safeKeys(), self::sensitiveKeys());
    }

    /**
     * Scalars guaranteed NOT to be flagged by the default value matcher:
     * small integers, booleans and short lower-case ASCII strings.
     */
    /**
     * The same value matcher the builder uses by default (regex + Luhn card).
     */
    public static function defaultValueMatcher(): ValueMatcherInterface
    {
        return new ChainValueMatcher(RegexValueMatcher::withDefaults(), new CreditCardMatcher());
    }

    public static function safeScalars(): Set
    {
        $matcher = self::defaultValueMatcher();

        return Set::either(
            Set::integers()->between(0, 9999),
            Set::of(true, false),
            Set::strings()->madeOf(Set::of(...range('a', 'z')))->between(1, 8),
        )->filter(static fn (mixed $v): bool => !\is_string($v) || !$matcher->matches($v));
    }

    /**
     * Strings that ARE flagged by the default value matcher (email, card, IBAN,
     * JWT, Bearer/Basic). Filtered through the real matcher so only genuine
     * positives survive.
     */
    public static function sensitiveValues(): Set
    {
        $matcher = self::defaultValueMatcher();

        return Set::either(
            Set::email(),
            Set::of('4242424242424242', '378282246310005', '5555555555554444'),
            Set::of('FR7630006000011234567890189', 'DE89370400440532013000', 'GB29NWBK60161331926819'),
            Set::of('eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxIn0.abc123', 'eyJ0eXAiOiJKV1QifQ.eyJpZCI6Mn0.def-456_x'),
            Set::of('Bearer abc.def-123', 'Basic dXNlcjpwYXNz'),
        )->filter(static fn (mixed $v): bool => \is_string($v) && $matcher->matches($v));
    }

    public static function anyScalar(): Set
    {
        return Set::either(self::safeScalars(), self::sensitiveValues());
    }

    /**
     * A flat associative array built from $keys => $values entries.
     */
    private static function arrayOf(Set $keys, Set $values): Set
    {
        return Set::sequence(Set::compose(static fn ($k, $v): array => [$k, $v], $keys, $values))
            ->between(0, 5)
            ->map(static function (array $pairs): array {
                $out = [];
                foreach ($pairs as [$key, $value]) {
                    $out[$key] = $value;
                }

                return $out;
            });
    }

    /**
     * Random nested associative arrays (depth up to 3) mixing safe/sensitive
     * keys and scalar/array values — exercises recursion, key and value masking.
     */
    public static function nestedArray(): Set
    {
        $level0 = self::arrayOf(self::anyKey(), self::anyScalar());
        $level1 = self::arrayOf(self::anyKey(), Set::either(self::anyScalar(), $level0));

        return self::arrayOf(self::anyKey(), Set::either(self::anyScalar(), $level1));
    }

    /**
     * Same shape as {@see nestedArray()} but using ONLY safe keys and safe
     * scalars — nothing in it should ever be masked.
     */
    public static function safeNestedArray(): Set
    {
        $level0 = self::arrayOf(self::safeKeys(), self::safeScalars());
        $level1 = self::arrayOf(self::safeKeys(), Set::either(self::safeScalars(), $level0));

        return self::arrayOf(self::safeKeys(), Set::either(self::safeScalars(), $level1));
    }
}
