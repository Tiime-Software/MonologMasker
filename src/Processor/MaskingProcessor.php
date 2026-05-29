<?php

declare(strict_types=1);

namespace Tiime\MonologMasker\Processor;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Tiime\MonologMasker\Masker\MaskerInterface;

/**
 * Thin Monolog adapter: runs the {@see MaskerInterface} over a record's
 * `context` and `extra` before it reaches any handler.
 *
 * LogRecord is immutable in Monolog 3, so a new record is returned via
 * {@see LogRecord::with()}.
 */
final class MaskingProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MaskerInterface $masker,
    ) {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->masker->mask($record->context),
            extra: $this->masker->mask($record->extra),
        );
    }
}
