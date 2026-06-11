<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Values\RecordedEvent;

/**
 * Destination for raw exposure, conversion, and metric events. The append-only
 * event stream is the source of truth; rollups are derived from it. Default
 * implementations may buffer and batch-insert, or forward to an external
 * analytical store, without the rest of the framework knowing.
 */
interface EventSink
{
    public function record(RecordedEvent $event): void;

    /**
     * @param iterable<RecordedEvent> $events
     */
    public function recordBatch(iterable $events): void;
}
