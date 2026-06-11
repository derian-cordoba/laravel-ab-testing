<?php

declare(strict_types=1);

namespace ABTests\Infrastructure;

use ABTests\Contracts\EventSink;
use ABTests\Values\RecordedEvent;

/**
 * No-op event sink. Discards all events silently. Useful as the default
 * binding before the queued/batch sink (and the underlying database tables)
 * are in place.
 */
final readonly class NullEventSink implements EventSink
{
    public function record(RecordedEvent $event): void
    {
        //
    }

    public function recordBatch(iterable $events): void
    {
        //
    }
}
