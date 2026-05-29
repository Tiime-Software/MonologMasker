<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Tiime\MonologMasker\Masker\MaskerInterface;

/**
 * Thin Monolog adapter: runs the {@see MaskerInterface} over a record's
 * `context` and `extra` — and, when enabled (default), over the `message`
 * itself — before it reaches any handler.
 *
 * Push this processor FIRST so Monolog runs it LAST: that way it also masks the
 * `extra` data added by other processors (Monolog executes processors in
 * reverse push order).
 *
 * LogRecord is immutable in Monolog 3, so a new record is returned via
 * {@see LogRecord::with()}.
 */
final class MaskingProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MaskerInterface $masker,
        private readonly bool $maskMessage = true,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->maskMessage ? $this->masker->maskString($record->message) : $record->message,
            context: $this->masker->mask($record->context),
            extra: $this->masker->mask($record->extra),
        );
    }
}
