<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Property;

use Innmind\BlackBox\PHPUnit\BlackBox;
use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Masker\Masker;
use Tiime\MonologMasker\MaskerBuilder;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;

/**
 * Black-box property tests for the masking engine: invariants that must hold
 * for ANY generated input, not just hand-picked examples.
 */
final class MaskerPropertyTest extends TestCase
{
    use BlackBox;

    private const MASK = '##MASKED##';

    private function masker(int $maxDepth = 16): Masker
    {
        return MaskerBuilder::create()
            ->withStrategy(new FullMaskStrategy(self::MASK))
            ->maxDepth($maxDepth)
            ->buildMasker();
    }

    public function testIsIdempotent(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $input): void {
                $masker = $this->masker();
                $once = $masker->mask($input);

                $this->assertSame($once, $masker->mask($once));
            });
    }

    public function testNoValueUnderASensitiveKeySurvives(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $input): void {
                $this->assertSensitiveKeysMasked($this->masker()->mask($input));
            });
    }

    public function testPreservesKeyStructure(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $input): void {
                $this->assertKeysPreserved($input, $this->masker()->mask($input));
            });
    }

    public function testDoesNotMutateInput(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $input): void {
                $snapshot = $input;
                $this->masker()->mask($input);

                $this->assertSame($snapshot, $input);
            });
    }

    public function testIsDeterministic(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $input): void {
                $masker = $this->masker();

                $this->assertSame($masker->mask($input), $masker->mask($input));
            });
    }

    public function testNeverNestsDeeperThanMaxDepth(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $input): void {
                $this->assertLessThanOrEqual(2, $this->arrayDepth($this->masker(2)->mask($input)));
            });
    }

    public function testLeavesSafeStructuresUntouched(): void
    {
        $this
            ->forAll(Generators::safeNestedArray())
            ->then(function (array $input): void {
                $this->assertSame($input, $this->masker()->mask($input));
            });
    }

    public function testMasksAnySensitiveValueUnderASafeKey(): void
    {
        $this
            ->forAll(Generators::safeKeys(), Generators::sensitiveValues())
            ->then(function (string $key, string $value): void {
                $this->assertSame(self::MASK, $this->masker()->mask([$key => $value])[$key]);
            });
    }

    public function testLeavesSafeScalarUnderSafeKeyUntouched(): void
    {
        $this
            ->forAll(Generators::safeKeys(), Generators::safeScalars())
            ->then(function (string $key, mixed $value): void {
                $this->assertSame([$key => $value], $this->masker()->mask([$key => $value]));
            });
    }

    /**
     * @param array<array-key, mixed> $output
     */
    private function assertSensitiveKeysMasked(array $output): void
    {
        $keyMatcher = KeyListMatcher::withDefaults();

        foreach ($output as $key => $value) {
            if (\is_string($key) && $keyMatcher->matches($key)) {
                $this->assertSame(self::MASK, $value);

                continue;
            }

            if (\is_array($value)) {
                $this->assertSensitiveKeysMasked($value);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $input
     * @param array<array-key, mixed> $output
     */
    private function assertKeysPreserved(array $input, array $output): void
    {
        $this->assertSame(array_keys($input), array_keys($output));

        foreach ($input as $key => $value) {
            if (\is_array($value) && \is_array($output[$key] ?? null)) {
                $this->assertKeysPreserved($value, $output[$key]);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function arrayDepth(array $data): int
    {
        $max = 1;
        foreach ($data as $value) {
            if (\is_array($value)) {
                $max = max($max, 1 + $this->arrayDepth($value));
            }
        }

        return $max;
    }
}
