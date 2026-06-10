<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Masker\Masker;
use Tiime\MonologMasker\MaskerBuilder;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Processor\MaskingProcessor;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;

final class MaskerBuilderTest extends TestCase
{
    public function testBuildsProcessorWithDefaults(): void
    {
        $processor = MaskerBuilder::create()->buildProcessor();

        self::assertInstanceOf(MaskingProcessor::class, $processor);
    }

    public function testCustomKeysAndStrategyAreApplied(): void
    {
        $masker = MaskerBuilder::create()
            ->withSensitiveKeys(['x-internal-token'])
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['x-internal-token' => 'abc', 'foo' => 'bar']);

        self::assertSame('***', $result['x-internal-token']);
        self::assertSame('bar', $result['foo']);
    }

    public function testWithoutValueMatchingDisablesPatternDetection(): void
    {
        $masker = MaskerBuilder::create()
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['contact' => 'john.doe@example.com']);

        self::assertSame('john.doe@example.com', $result['contact']);
    }

    public function testBuilderIsImmutable(): void
    {
        $base = MaskerBuilder::create();
        $derived = $base->withSensitiveKeys(['extra_key']);

        self::assertNotSame($base, $derived);
    }

    public function testWithKeyMatcherReplacesDefaultKeys(): void
    {
        $masker = MaskerBuilder::create()
            ->withKeyMatcher(new KeyListMatcher(['only_this']))
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['password' => 'p', 'only_this' => 'x']);

        // A default key like "password" is no longer matched once replaced.
        self::assertSame('p', $result['password']);
        self::assertSame('***', $result['only_this']);
    }

    public function testWithValueMatcherReplacesDefaultPatterns(): void
    {
        $masker = MaskerBuilder::create()
            ->withValueMatcher(new RegexValueMatcher(['/\bsecretword\b/']))
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['a' => 'john.doe@example.com', 'b' => 'a secretword here']);

        // Default email pattern is gone; only the custom pattern applies, and
        // only the matched sub-string is masked.
        self::assertSame('john.doe@example.com', $result['a']);
        self::assertSame('a *** here', $result['b']);
    }

    public function testWithValuePatternsAddsToDefaults(): void
    {
        $masker = MaskerBuilder::create()
            ->withValuePatterns(['fr_phone' => '/\b0[1-9](?:\d{2}){4}\b/'])
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['phone' => '0612345678', 'email' => 'john.doe@example.com']);

        // Both the added pattern and the defaults are active.
        self::assertSame('***', $result['phone']);
        self::assertSame('***', $result['email']);
    }

    public function testMaxDepthIsApplied(): void
    {
        $masker = MaskerBuilder::create()
            ->maxDepth(1)
            ->buildMasker();

        $result = $masker->mask(['a' => ['b' => 'c']]);

        self::assertSame(['a' => Masker::TRUNCATED], $result);
    }

    public function testDerivingDoesNotMutateOriginalBuilder(): void
    {
        $base = MaskerBuilder::create()->withStrategy(new FullMaskStrategy('***'));
        $base->withoutValueMatching();

        // The original still has value matching enabled.
        $result = $base->buildMasker()->mask(['contact' => 'john.doe@example.com']);

        self::assertSame('***', $result['contact']);
    }

    public function testWithSensitiveKeysAcceptsSeveralKeysAtOnce(): void
    {
        $masker = MaskerBuilder::create()
            ->withSensitiveKeys(['ka', 'kb'])
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['ka' => '1', 'kb' => '2']);

        self::assertSame(['ka' => '***', 'kb' => '***'], $result);
    }

    public function testWithSensitiveKeysAccumulatesAcrossCalls(): void
    {
        $masker = MaskerBuilder::create()
            ->withSensitiveKeys(['ka'])
            ->withSensitiveKeys(['kb'])
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['ka' => '1', 'kb' => '2']);

        self::assertSame(['ka' => '***', 'kb' => '***'], $result);
    }

    public function testWithValuePatternsAccumulatesAcrossCalls(): void
    {
        $masker = MaskerBuilder::create()
            ->withValuePatterns(['a' => '/\baaa\b/'])
            ->withValuePatterns(['b' => '/\bbbb\b/'])
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['x' => 'aaa', 'y' => 'bbb']);

        self::assertSame(['x' => '***', 'y' => '***'], $result);
    }

    public function testLatestKeyMatcherWins(): void
    {
        $masker = MaskerBuilder::create()
            ->withKeyMatcher(new KeyListMatcher(['aaa']))
            ->withKeyMatcher(new KeyListMatcher(['bbb']))
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['aaa' => '1', 'bbb' => '2']);

        self::assertSame(['aaa' => '1', 'bbb' => '***'], $result);
    }

    public function testLatestValueMatcherWins(): void
    {
        $masker = MaskerBuilder::create()
            ->withValueMatcher(new RegexValueMatcher(['/\baaa\b/']))
            ->withValueMatcher(new RegexValueMatcher(['/\bbbb\b/']))
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['x' => 'aaa', 'y' => 'bbb']);

        self::assertSame(['x' => 'aaa', 'y' => '***'], $result);
    }

    public function testLatestStrategyWins(): void
    {
        $masker = MaskerBuilder::create()
            ->withStrategy(new FullMaskStrategy('###'))
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['password' => 'x']);

        self::assertSame(['password' => '***'], $result);
    }

    public function testSegmentMatchingCatchesCompoundKeysByDefault(): void
    {
        $masker = MaskerBuilder::create()
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['db_password' => 'x', 'userToken' => 'y', 'username' => 'z']);

        self::assertSame(['db_password' => '***', 'userToken' => '***', 'username' => 'z'], $result);
    }

    public function testMatchKeysExactlyRestoresWholeStringMatching(): void
    {
        $masker = MaskerBuilder::create()
            ->matchKeysExactly()
            ->withoutValueMatching()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['db_password' => 'x', 'password' => 'y']);

        // Compound key no longer matched once exact matching is on.
        self::assertSame(['db_password' => 'x', 'password' => '***'], $result);
    }

    public function testDetectsLuhnValidCardByDefault(): void
    {
        $masker = MaskerBuilder::create()->withStrategy(new FullMaskStrategy('***'))->buildMasker();

        self::assertSame(['ref' => '***'], $masker->mask(['ref' => '4242424242424242']));
        // A non-Luhn digit run is left alone.
        self::assertSame(['ref' => '1234567890123'], $masker->mask(['ref' => '1234567890123']));
    }

    public function testObjectTraversalCanBeDisabled(): void
    {
        $object = new \stdClass();
        $object->password = 'secret';
        $masker = MaskerBuilder::create()->traverseObjects(false)->buildMasker();

        self::assertSame($object, $masker->mask(['o' => $object])['o']);
    }

    public function testMessageMaskingCanBeDisabledViaBuilder(): void
    {
        $processor = MaskerBuilder::create()
            ->maskMessage(false)
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildProcessor();

        self::assertSame('mail john.doe@example.com', $processor($this->record())->message);
    }

    public function testMasksMessageByDefaultViaBuilder(): void
    {
        $processor = MaskerBuilder::create()->withStrategy(new FullMaskStrategy('***'))->buildProcessor();

        self::assertSame('mail ***', $processor($this->record())->message);
    }

    public function testMessageMaskingCanBeReEnabledWithNoArgument(): void
    {
        $processor = MaskerBuilder::create()
            ->maskMessage(false)
            ->maskMessage()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildProcessor();

        self::assertSame('mail ***', $processor($this->record())->message);
    }

    public function testTraversesObjectsByDefaultViaBuilder(): void
    {
        $object = new \stdClass();
        $object->password = 'secret';
        $masker = MaskerBuilder::create()->withStrategy(new FullMaskStrategy('***'))->buildMasker();

        self::assertSame(['o' => ['password' => '***']], $masker->mask(['o' => $object]));
    }

    public function testObjectTraversalCanBeReEnabledWithNoArgument(): void
    {
        $object = new \stdClass();
        $object->password = 'secret';
        $masker = MaskerBuilder::create()
            ->traverseObjects(false)
            ->traverseObjects()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        self::assertSame(['o' => ['password' => '***']], $masker->mask(['o' => $object]));
    }

    public function testWithJsonKeysMasksInsideJsonStringValue(): void
    {
        $masker = MaskerBuilder::create()
            ->withJsonKeys(['body'])
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['body' => '{"password":"x","ok":"y"}']);

        self::assertSame(['body' => '{"password":"***","ok":"y"}'], $result);
    }

    public function testWithJsonKeysAcceptsSeveralKeysAtOnce(): void
    {
        $masker = MaskerBuilder::create()
            ->withJsonKeys(['body', 'payload'])
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask([
            'body' => '{"password":"x"}',
            'payload' => '{"token":"y"}',
        ]);

        self::assertSame([
            'body' => '{"password":"***"}',
            'payload' => '{"token":"***"}',
        ], $result);
    }

    public function testWithJsonKeysAccumulatesAcrossCalls(): void
    {
        $masker = MaskerBuilder::create()
            ->withJsonKeys(['body'])
            ->withJsonKeys(['payload'])
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask([
            'body' => '{"password":"x"}',
            'payload' => '{"token":"y"}',
        ]);

        self::assertSame([
            'body' => '{"password":"***"}',
            'payload' => '{"token":"***"}',
        ], $result);
    }

    public function testWithoutJsonKeysLeavesJsonStringsUntouched(): void
    {
        $masker = MaskerBuilder::create()
            ->withStrategy(new FullMaskStrategy('***'))
            ->buildMasker();

        $result = $masker->mask(['body' => '{"password":"x"}']);

        self::assertSame(['body' => '{"password":"x"}'], $result);
    }

    private function record(): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable('@0'),
            channel: 'app',
            level: Level::Info,
            message: 'mail john.doe@example.com',
        );
    }

    public function testDefaultMaxDepthIsSixteen(): void
    {
        $masker = MaskerBuilder::create()->buildMasker();

        $node = ['leaf' => 'x'];
        for ($i = 0; $i < 20; ++$i) {
            $node = ['next' => $node];
        }

        $depth = 1;
        $cursor = $masker->mask($node)['next'] ?? null;
        while (\is_array($cursor)) {
            ++$depth;
            $cursor = $cursor['next'] ?? null;
        }

        self::assertSame(16, $depth);
    }
}
