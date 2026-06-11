<?php

declare(strict_types=1);

namespace ABTests\Testing;

use ABTests\Contracts\EventSink;
use ABTests\Enums\EventType;
use ABTests\Values\RecordedEvent;

/**
 * An in-memory EventSink that stores every recorded event so that assertions
 * can inspect them after the fact. Used exclusively by FakeExperiments.
 */
final class RecordingEventSink implements EventSink
{
    /** @var list<RecordedEvent> */
    private array $events = [];

    public function record(RecordedEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @param iterable<RecordedEvent> $events */
    public function recordBatch(iterable $events): void
    {
        foreach ($events as $event) {
            $this->events[] = $event;
        }
    }

    /** @return list<RecordedEvent> */
    public function allEvents(): array
    {
        return $this->events;
    }

    /** @return list<RecordedEvent> */
    public function ofType(EventType $type): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (RecordedEvent $e): bool => $e->type === $type,
        ));
    }

    /**
     * Return all exposure events for the given experiment key and unit key.
     *
     * @return list<RecordedEvent>
     */
    public function exposuresFor(string $experimentKey, string $unitKey): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (RecordedEvent $e): bool =>
                $e->type === EventType::exposure
                && $e->experimentKey === $experimentKey
                && $e->unitKey === $unitKey,
        ));
    }

    /**
     * Return all metric/conversion events for the given metric key and unit key.
     *
     * @return list<RecordedEvent>
     */
    public function conversionsFor(string $metricKey, string $unitKey): array
    {
        return array_values(array_filter(
            $this->events,
            static fn (RecordedEvent $e): bool =>
                $e->type === EventType::metric
                && $e->metricKey === $metricKey
                && $e->unitKey === $unitKey,
        ));
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
