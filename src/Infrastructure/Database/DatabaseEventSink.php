<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\EventSink;
use ABTests\Infrastructure\Database\Models\EventModel;
use ABTests\Values\RecordedEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Database-backed event sink. Buffers RecordedEvent objects in-process and
 * flushes them as a batch insert on request termination (via the service
 * provider's terminating() callback). Idempotency is guaranteed by the
 * UNIQUE(idempotency_key) constraint — duplicates are silently discarded.
 */
final class DatabaseEventSink implements EventSink
{
    /** @var list<RecordedEvent> */
    private array $buffer = [];

    public function record(RecordedEvent $event): void
    {
        $this->buffer[] = $event;
    }

    public function recordBatch(iterable $events): void
    {
        foreach ($events as $event) {
            $this->buffer[] = $event;
        }
    }

    /**
     * Flush all buffered events to the database in chunks of 500.
     * Called by the service provider terminating() hook after each request.
     */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $rows = array_map(
            static fn (RecordedEvent $event): array => [
                'experiment_key' => $event->experimentKey,
                'unit_type' => $event->unitType,
                'unit_key' => $event->unitKey,
                'variant_key' => $event->variantKey,
                'type' => $event->type->value,
                'metric_key' => $event->metricKey,
                'value' => $event->value,
                'properties' => $event->properties !== [] ? json_encode($event->properties) : null,
                'idempotency_key' => $event->idempotencyKey,
                'occurred_at' => $event->occurredAt->format('Y-m-d H:i:s'),
            ],
            $this->buffer,
        );

        $this->buffer = [];

        foreach (array_chunk($rows, 500) as $chunk) {
            try {
                EventModel::query()->insert($chunk);
            } catch (UniqueConstraintViolationException) {
                // Batch contained duplicates; fall back to per-row insertOrIgnore.
                foreach ($chunk as $row) {
                    EventModel::query()->insertOrIgnore([$row]);
                }
            }
        }
    }
}
