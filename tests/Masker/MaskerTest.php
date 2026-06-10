<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Masker;

use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Masker\Masker;
use Tiime\MonologMasker\Matcher\CreditCardMatcher;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;
use Tiime\MonologMasker\Strategy\MaskStrategyInterface;

final class MaskerTest extends TestCase
{
    private const MASK = '***';

    /**
     * @param list<string> $jsonKeys
     */
    private function masker(int $maxDepth = 16, array $jsonKeys = []): Masker
    {
        return new Masker(
            new KeyListMatcher(['password', 'token']),
            RegexValueMatcher::withDefaults(),
            new FullMaskStrategy(self::MASK),
            $maxDepth,
            jsonKeyMatcher: [] === $jsonKeys ? null : new KeyListMatcher($jsonKeys),
        );
    }

    public function testMasksValueOfSensitiveKey(): void
    {
        $result = $this->masker()->mask(['username' => 'alice', 'password' => 'hunter2']);

        self::assertSame(['username' => 'alice', 'password' => self::MASK], $result);
    }

    public function testMasksSensitiveKeyRecursively(): void
    {
        $result = $this->masker()->mask([
            'user' => [
                'name' => 'alice',
                'credentials' => ['password' => 'hunter2'],
            ],
        ]);

        self::assertSame([
            'user' => [
                'name' => 'alice',
                'credentials' => ['password' => self::MASK],
            ],
        ], $result);
    }

    public function testMasksWholeSubtreeUnderSensitiveKey(): void
    {
        $result = $this->masker()->mask([
            'token' => ['access' => 'abc', 'refresh' => 'def'],
        ]);

        // The entire array under a sensitive key collapses to the placeholder.
        self::assertSame(self::MASK, $result['token']);
    }

    public function testMasksValueDetectedByPattern(): void
    {
        $result = $this->masker()->mask(['contact' => 'john.doe@example.com']);

        self::assertSame(self::MASK, $result['contact']);
    }

    public function testLeavesHarmlessValuesUntouched(): void
    {
        $input = ['level' => 'info', 'count' => 3, 'enabled' => true];

        self::assertSame($input, $this->masker()->mask($input));
    }

    public function testDoesNotMutateInput(): void
    {
        $input = ['password' => 'hunter2', 'nested' => ['token' => 'abc']];
        $snapshot = $input;

        $this->masker()->mask($input);

        self::assertSame($snapshot, $input);
    }

    public function testTruncatesBeyondMaxDepth(): void
    {
        $result = $this->masker(2)->mask(['a' => ['b' => ['c' => 'deep']]]);

        self::assertSame(['a' => ['b' => Masker::TRUNCATED]], $result);
    }

    public function testHandlesSelfReferentialArrayWithoutInfiniteLoop(): void
    {
        $data = ['name' => 'loop'];
        $data['self'] = &$data;

        $result = $this->masker(5)->mask($data);

        self::assertSame('loop', $result['name']);
        // Depth guard kicks in before exhausting the stack.
        $this->addToAssertionCount(1);
    }

    public function testValueMatchingCanBeDisabled(): void
    {
        $masker = new Masker(
            new KeyListMatcher(['password']),
            null,
            new FullMaskStrategy(self::MASK),
        );

        $result = $masker->mask(['contact' => 'john.doe@example.com', 'password' => 'x']);

        self::assertSame('john.doe@example.com', $result['contact']);
        self::assertSame(self::MASK, $result['password']);
    }

    public function testRejectsInvalidMaxDepth(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Masker(new KeyListMatcher([]), null, new FullMaskStrategy(), 0);
    }

    /**
     * Asserts the exact string handed to the strategy for every value type
     * found under a sensitive key. A capturing strategy is used on purpose:
     * with FullMaskStrategy every branch would collapse to the same placeholder
     * and the per-type casting logic could not be verified.
     */
    public function testCastsSensitiveValueToStringBeforeMasking(): void
    {
        $captor = new class implements MaskStrategyInterface {
            /** @var list<string> */
            public array $seen = [];

            public function mask(string $value): string
            {
                $this->seen[] = $value;

                return 'X';
            }
        };

        $masker = new Masker(new KeyListMatcher(['secret']), null, $captor);

        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'ST';
            }
        };

        $masker->mask(['secret' => 'plain']);
        $masker->mask(['secret' => true]);
        $masker->mask(['secret' => false]);
        $masker->mask(['secret' => 42]);
        $masker->mask(['secret' => 3.5]);
        $masker->mask(['secret' => null]);
        $masker->mask(['secret' => $stringable]);
        $masker->mask(['secret' => new \stdClass()]);
        $masker->mask(['secret' => ['nested' => 'x']]);

        self::assertSame(
            ['plain', 'true', 'false', '42', '3.5', '', 'ST', '', ''],
            $captor->seen,
        );
    }

    public function testKeyMatchTakesPriorityOverValueMatch(): void
    {
        // 'password' is sensitive AND its value also matches the email pattern:
        // it must be masked exactly once via the key path.
        $result = $this->masker()->mask(['password' => 'john.doe@example.com']);

        self::assertSame(['password' => self::MASK], $result);
    }

    public function testMasksMatchingValueDeepInsideNonSensitiveArrays(): void
    {
        $result = $this->masker()->mask([
            'meta' => ['contacts' => ['primary' => 'john.doe@example.com', 'label' => 'home']],
        ]);

        self::assertSame([
            'meta' => ['contacts' => ['primary' => self::MASK, 'label' => 'home']],
        ], $result);
    }

    public function testMasksValuesInSequentialArrays(): void
    {
        $result = $this->masker()->mask(['recipients' => ['john.doe@example.com', 'safe-label']]);

        self::assertSame(['recipients' => [self::MASK, 'safe-label']], $result);
    }

    public function testReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], $this->masker()->mask([]));
    }

    public function testHandlesNumericStringKeys(): void
    {
        // PHP casts numeric string keys to integers; they must never be treated
        // as sensitive and their values are still scanned.
        $result = $this->masker()->mask(['0' => 'john.doe@example.com', '1' => 'safe']);

        self::assertSame([0 => self::MASK, 1 => 'safe'], $result);
    }

    public function testMaxDepthOfOneTruncatesFirstNestedArray(): void
    {
        $result = $this->masker(1)->mask(['a' => ['b' => 'c']]);

        self::assertSame(['a' => Masker::TRUNCATED], $result);
    }

    public function testLeavesScalarsNotRecognisedByTheMatcherUntouched(): void
    {
        // This matcher (default regex patterns) does not recognise bare digit
        // runs, so the integer passes through unchanged.
        $result = $this->masker()->mask(['ref' => 4242424242424242]);

        self::assertSame(['ref' => 4242424242424242], $result);
    }

    public function testMasksIntegerValueWhenTheMatcherRecognisesIt(): void
    {
        $masker = new Masker(new KeyListMatcher([]), new CreditCardMatcher(), new FullMaskStrategy(self::MASK));

        self::assertSame(['card' => self::MASK], $masker->mask(['card' => 4242424242424242]));
    }

    public function testMasksOnlyTheMatchedSubStringInAStringLeaf(): void
    {
        $result = $this->masker()->mask(['note' => 'mail john.doe@example.com ok']);

        self::assertSame(['note' => 'mail '.self::MASK.' ok'], $result);
    }

    public function testTraversesObjectPublicProperties(): void
    {
        $object = new class {
            public string $password = 'secret';
            public string $name = 'alice';
        };

        self::assertSame(
            ['u' => ['password' => self::MASK, 'name' => 'alice']],
            $this->masker()->mask(['u' => $object]),
        );
    }

    public function testTraversesJsonSerializableObjects(): void
    {
        $object = new class implements \JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['token' => 'abc', 'x' => 1];
            }
        };

        self::assertSame(
            ['o' => ['token' => self::MASK, 'x' => 1]],
            $this->masker()->mask(['o' => $object]),
        );
    }

    public function testMasksStringableObjectsByValue(): void
    {
        $object = new class implements \Stringable {
            public function __toString(): string
            {
                return 'john.doe@example.com';
            }
        };

        self::assertSame(['o' => self::MASK], $this->masker()->mask(['o' => $object]));
    }

    public function testGuardsAgainstObjectCycles(): void
    {
        $object = new class {
            public string $name = 'loop';
            public ?object $self = null;
        };
        $object->self = $object;

        self::assertSame(
            ['o' => ['name' => 'loop', 'self' => Masker::TRUNCATED]],
            $this->masker()->mask(['o' => $object]),
        );
    }

    public function testDoesNotTraverseObjectsWhenDisabled(): void
    {
        $object = new \stdClass();
        $object->password = 'secret';
        $masker = new Masker(
            new KeyListMatcher(['password']),
            RegexValueMatcher::withDefaults(),
            new FullMaskStrategy(self::MASK),
            16,
            false,
        );

        self::assertSame($object, $masker->mask(['o' => $object])['o']);
    }

    public function testMaskStringRedactsSensitiveTokens(): void
    {
        self::assertSame(
            'email '.self::MASK.' end',
            $this->masker()->maskString('email john.doe@example.com end'),
        );
    }

    public function testMaskStringReturnsValueUnchangedWithoutValueMatcher(): void
    {
        $masker = new Masker(new KeyListMatcher([]), null, new FullMaskStrategy(self::MASK));

        self::assertSame('plain text', $masker->maskString('plain text'));
    }

    public function testTruncatesObjectsBeyondMaxDepth(): void
    {
        $masker = new Masker(new KeyListMatcher([]), null, new FullMaskStrategy(self::MASK), 1);

        self::assertSame(['o' => Masker::TRUNCATED], $masker->mask(['o' => new \stdClass()]));
    }

    public function testGuardsAgainstJsonSerializableCycles(): void
    {
        $object = new class implements \JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['self' => $this];
            }
        };

        self::assertSame(
            ['o' => ['self' => Masker::TRUNCATED]],
            $this->masker()->mask(['o' => $object]),
        );
    }

    public function testDetachesTraversedObjectsSoSiblingsAreNotTruncated(): void
    {
        $object = new class {
            public string $name = 'ok';
        };

        self::assertSame(
            ['a' => ['name' => 'ok'], 'b' => ['name' => 'ok']],
            $this->masker()->mask(['a' => $object, 'b' => $object]),
        );
    }

    public function testDetachesJsonSerializableSoSiblingsAreNotTruncated(): void
    {
        $object = new class implements \JsonSerializable {
            /**
             * @return array<string, mixed>
             */
            public function jsonSerialize(): array
            {
                return ['token' => 'abc'];
            }
        };

        self::assertSame(
            ['a' => ['token' => self::MASK], 'b' => ['token' => self::MASK]],
            $this->masker()->mask(['a' => $object, 'b' => $object]),
        );
    }

    public function testCountsObjectDepthTowardsMaxDepth(): void
    {
        $object = new class {
            /** @var array<string, mixed> */
            public array $a = ['b' => ['c' => 'd']];
        };
        $masker = new Masker(new KeyListMatcher([]), null, new FullMaskStrategy(self::MASK), 3);

        self::assertSame(
            ['root' => ['a' => ['b' => Masker::TRUNCATED]]],
            $masker->mask(['root' => $object]),
        );
    }

    public function testDoesNotMutateNestedInput(): void
    {
        $input = ['outer' => ['inner' => ['password' => 'hunter2', 'safe' => 'keep']]];
        $snapshot = ['outer' => ['inner' => ['password' => 'hunter2', 'safe' => 'keep']]];

        $this->masker()->mask($input);

        self::assertSame($snapshot, $input);
    }

    public function testPreservesStructureStrictlyWithinMaxDepth(): void
    {
        // With maxDepth=3, a 3-level structure is fully preserved (no truncation).
        $result = $this->masker(3)->mask(['a' => ['b' => ['c' => 'deep']]]);

        self::assertSame(['a' => ['b' => ['c' => 'deep']]], $result);
    }

    public function testKeepsKeysFollowingANestedArray(): void
    {
        // Guards the `continue` after the array branch: keys after a nested
        // array must still be processed.
        $result = $this->masker()->mask([
            'nested' => ['x' => 'y'],
            'tail' => 'john.doe@example.com',
        ]);

        self::assertSame(['nested' => ['x' => 'y'], 'tail' => self::MASK], $result);
    }

    public function testDefaultMaxDepthIsSixteen(): void
    {
        $masker = new Masker(new KeyListMatcher([]), null, new FullMaskStrategy());

        $result = $masker->mask(self::nestedArray(20));

        self::assertSame(16, self::preservedDepth($result));
    }

    public function testMasksSensitiveKeyInsideJsonStringValue(): void
    {
        // A declared JSON key is decoded, masked in depth, then re-encoded.
        // A sibling string key that is NOT a JSON key follows the normal rules.
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => '{"username":"alice","password":"hunter2"}',
            'channel' => 'web',
        ]);

        self::assertSame([
            'body' => '{"username":"alice","password":"***"}',
            'channel' => 'web',
        ], $result);
    }

    public function testMasksValueMatchedByPatternInsideJsonStringValue(): void
    {
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => '{"contact":"john.doe@example.com"}',
        ]);

        self::assertSame(['body' => '{"contact":"***"}'], $result);
    }

    public function testMasksNestedJsonStructureInsideStringValue(): void
    {
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => '{"user":{"name":"alice","password":"x"}}',
        ]);

        self::assertSame(['body' => '{"user":{"name":"alice","password":"***"}}'], $result);
    }

    public function testJsonInsideStringValueRespectsMaxDepth(): void
    {
        // The decoded structure is processed at depth+1, so the depth budget
        // keeps applying across the JSON boundary.
        $result = $this->masker(2, ['body'])->mask([
            'body' => '{"a":{"b":"c"}}',
        ]);

        self::assertSame(['body' => '{"a":"[TRUNCATED]"}'], $result);
    }

    public function testJsonInsideStringValuePreservedWhenWithinMaxDepth(): void
    {
        // Same payload, one more depth level available: the decoded structure is
        // fully preserved — pins the exact depth+1 budget across the boundary.
        $result = $this->masker(3, ['body'])->mask([
            'body' => '{"a":{"b":"c"}}',
        ]);

        self::assertSame(['body' => '{"a":{"b":"c"}}'], $result);
    }

    public function testJsonReEncodingLeavesSlashesAndUnicodeUnescaped(): void
    {
        // Re-encoding keeps payloads readable: slashes and non-ASCII characters
        // are not escaped to \/ or \uXXXX.
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => '{"url":"a/b","note":"é"}',
        ]);

        self::assertSame(['body' => '{"url":"a/b","note":"é"}'], $result);
    }

    public function testJsonWinsOverSensitiveKeyWhenValueIsValidJson(): void
    {
        // 'token' is BOTH sensitive and declared JSON: a valid JSON value is
        // looked into rather than collapsed.
        $result = $this->masker(jsonKeys: ['token'])->mask([
            'token' => '{"id":1,"password":"x"}',
        ]);

        self::assertSame(['token' => '{"id":1,"password":"***"}'], $result);
    }

    public function testJsonKeyWithInvalidJsonFallsBackToSensitiveCollapse(): void
    {
        // 'token' is sensitive AND declared JSON, but the value is not decodable:
        // it falls back to the normal rules, so the sensitive key collapses it.
        $result = $this->masker(jsonKeys: ['token'])->mask(['token' => 'not-json']);

        self::assertSame(['token' => self::MASK], $result);
    }

    public function testJsonKeyWithInvalidJsonFallsBackToLeafValueMatching(): void
    {
        // Not sensitive, not decodable JSON: the leaf value matcher still runs.
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => 'john.doe@example.com',
            'note' => 'plain text',
        ]);

        self::assertSame(['body' => self::MASK, 'note' => 'plain text'], $result);
    }

    public function testJsonKeyWithNonStringValueRecursesAsArray(): void
    {
        // A JSON key holding an actual array (already decoded) is recursed
        // normally — the JSON branch only applies to string values.
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => ['password' => 'x', 'safe' => 'y'],
        ]);

        self::assertSame(['body' => ['password' => self::MASK, 'safe' => 'y']], $result);
    }

    public function testJsonKeyWithScalarJsonFallsBack(): void
    {
        // A string decoding to a scalar (not an array) is not treated as a
        // structured payload; it falls back to the normal leaf rules.
        $result = $this->masker(jsonKeys: ['body'])->mask(['body' => '42']);

        self::assertSame(['body' => '42'], $result);
    }

    public function testMasksJsonListInsideStringValue(): void
    {
        $result = $this->masker(jsonKeys: ['body'])->mask([
            'body' => '["john.doe@example.com","safe"]',
        ]);

        self::assertSame(['body' => '["***","safe"]'], $result);
    }

    public function testWithoutJsonKeysLeavesJsonStringsUntouched(): void
    {
        // No JSON keys configured: a JSON string is just an opaque leaf.
        $result = $this->masker()->mask(['body' => '{"password":"x"}']);

        self::assertSame(['body' => '{"password":"x"}'], $result);
    }

    public function testIsIdempotentOnJsonStringValue(): void
    {
        $masker = $this->masker(jsonKeys: ['body']);
        $once = $masker->mask(['body' => '{"password":"hunter2","email":"john.doe@example.com"}']);

        self::assertSame($once, $masker->mask($once));
    }

    /**
     * Builds ['next' => ['next' => ... ['leaf' => 'x']]] nested $levels deep.
     *
     * @return array<string, mixed>
     */
    private static function nestedArray(int $levels): array
    {
        $node = ['leaf' => 'x'];
        for ($i = 0; $i < $levels; ++$i) {
            $node = ['next' => $node];
        }

        return $node;
    }

    /**
     * Counts how many nested array levels survived before the truncation marker.
     *
     * @param array<array-key, mixed> $data
     */
    private static function preservedDepth(array $data): int
    {
        $depth = 1;
        $cursor = $data['next'] ?? null;
        while (\is_array($cursor)) {
            ++$depth;
            $cursor = $cursor['next'] ?? null;
        }

        return $depth;
    }
}
