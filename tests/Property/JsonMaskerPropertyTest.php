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
 * Black-box property tests for JSON-in-a-string masking: a serialized payload
 * logged under a declared JSON key must be masked as thoroughly as a native
 * array would be, and the result must stay valid, re-decodable JSON.
 */
final class JsonMaskerPropertyTest extends TestCase
{
    use BlackBox;

    private const MASK = '##MASKED##';
    private const JSON_KEY = 'body';

    private function masker(): Masker
    {
        return MaskerBuilder::create()
            ->withJsonKeys([self::JSON_KEY])
            ->withStrategy(new FullMaskStrategy(self::MASK))
            ->buildMasker();
    }

    public function testReEncodesToValidJson(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $payload): void {
                $output = $this->masker()->mask([self::JSON_KEY => self::encode($payload)]);

                $this->assertIsString($output[self::JSON_KEY]);
                json_decode($output[self::JSON_KEY], true);
                $this->assertSame(\JSON_ERROR_NONE, json_last_error());
            });
    }

    public function testNoSensitiveKeySurvivesInsideTheJsonPayload(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $payload): void {
                $output = $this->masker()->mask([self::JSON_KEY => self::encode($payload)]);
                $decoded = json_decode($output[self::JSON_KEY], true);

                $this->assertSensitiveKeysMasked(\is_array($decoded) ? $decoded : []);
            });
    }

    public function testIsIdempotent(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $payload): void {
                $masker = $this->masker();
                $once = $masker->mask([self::JSON_KEY => self::encode($payload)]);

                $this->assertSame($once, $masker->mask($once));
            });
    }

    public function testDoesNotMutateInput(): void
    {
        $this
            ->forAll(Generators::nestedArray())
            ->then(function (array $payload): void {
                $input = [self::JSON_KEY => self::encode($payload)];
                $snapshot = $input;
                $this->masker()->mask($input);

                $this->assertSame($snapshot, $input);
            });
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        return json_encode($payload, \JSON_THROW_ON_ERROR);
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
}
