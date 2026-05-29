<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Tests\Processor;

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Tiime\MonologMasker\Masker\Masker;
use Tiime\MonologMasker\MaskerBuilder;
use Tiime\MonologMasker\Matcher\KeyListMatcher;
use Tiime\MonologMasker\Matcher\RegexValueMatcher;
use Tiime\MonologMasker\Processor\MaskingProcessor;
use Tiime\MonologMasker\Strategy\FullMaskStrategy;

final class MaskingProcessorTest extends TestCase
{
    public function testMasksContextAndExtra(): void
    {
        $processor = new MaskingProcessor(new Masker(
            new KeyListMatcher(['password']),
            RegexValueMatcher::withDefaults(),
            new FullMaskStrategy('***'),
        ));

        $record = new LogRecord(
            datetime: new \DateTimeImmutable('@0'),
            channel: 'app',
            level: Level::Info,
            message: 'login attempt',
            context: ['user' => 'alice', 'password' => 'hunter2'],
            extra: ['email' => 'alice@example.com'],
        );

        $processed = $processor($record);

        self::assertSame('alice', $processed->context['user']);
        self::assertSame('***', $processed->context['password']);
        self::assertSame('***', $processed->extra['email']);
        // Message is left untouched.
        self::assertSame('login attempt', $processed->message);
    }

    public function testEndToEndThroughLogger(): void
    {
        $handler = new TestHandler();
        $logger = new Logger('app');
        $logger->pushHandler($handler);
        $logger->pushProcessor(MaskerBuilder::create()->withStrategy(new FullMaskStrategy('***'))->buildProcessor());

        $logger->info('payment', [
            'card_number' => '4242 4242 4242 4242',
            'token' => 'super-secret',
            'amount' => 1000,
        ]);

        $records = $handler->getRecords();
        self::assertCount(1, $records);

        $context = $records[0]->context;
        self::assertSame('***', $context['card_number']);
        self::assertSame('***', $context['token']);
        self::assertSame(1000, $context['amount']);
    }

    public function testLeavesOriginalRecordUntouchedAndPreservesMetadata(): void
    {
        $processor = new MaskingProcessor(new Masker(
            new KeyListMatcher(['password']),
            RegexValueMatcher::withDefaults(),
            new FullMaskStrategy('***'),
        ));

        $datetime = new \DateTimeImmutable('@0');
        $record = new LogRecord(
            datetime: $datetime,
            channel: 'billing',
            level: Level::Warning,
            message: 'oops',
            context: ['password' => 'hunter2'],
            extra: [],
        );

        $processed = $processor($record);

        // Original record is not mutated (LogRecord is immutable in Monolog 3).
        self::assertSame('hunter2', $record->context['password']);

        // All non-masked fields are carried over unchanged.
        self::assertSame('***', $processed->context['password']);
        self::assertSame('billing', $processed->channel);
        self::assertSame(Level::Warning, $processed->level);
        self::assertSame('oops', $processed->message);
        self::assertSame($datetime, $processed->datetime);
    }

    public function testHandlesEmptyContextAndExtra(): void
    {
        $processor = new MaskingProcessor(new Masker(
            new KeyListMatcher(['password']),
            RegexValueMatcher::withDefaults(),
            new FullMaskStrategy('***'),
        ));

        $processed = $processor(new LogRecord(
            datetime: new \DateTimeImmutable('@0'),
            channel: 'app',
            level: Level::Debug,
            message: 'noop',
        ));

        self::assertSame([], $processed->context);
        self::assertSame([], $processed->extra);
    }

    public function testMasksTheMessageByDefault(): void
    {
        $processor = new MaskingProcessor($this->masker());

        $processed = $processor($this->record('login from john.doe@example.com'));

        self::assertSame('login from ***', $processed->message);
    }

    public function testMessageMaskingCanBeDisabled(): void
    {
        $processor = new MaskingProcessor($this->masker(), false);

        $processed = $processor($this->record('login from john.doe@example.com'));

        self::assertSame('login from john.doe@example.com', $processed->message);
    }

    private function masker(): Masker
    {
        return new Masker(
            new KeyListMatcher(['password']),
            RegexValueMatcher::withDefaults(),
            new FullMaskStrategy('***'),
        );
    }

    private function record(string $message): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable('@0'),
            channel: 'app',
            level: Level::Info,
            message: $message,
        );
    }
}
