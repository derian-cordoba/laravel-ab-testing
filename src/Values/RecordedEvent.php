<?php

declare(strict_types=1);

namespace ABTests\Values;

use DateTimeImmutable;
use ABTests\Enums\EventType;

/**
 * An immutable row destined for the append-only event store. The idempotency
 * key lets the sink discard duplicate fires (e.g. a re-rendered exposure)
 * without corrupting counts.
 */
final readonly class RecordedEvent
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public string $experimentKey,
        public string $unitType,
        public string $unitKey,
        public string $variantKey,
        public EventType $type,
        public string $idempotencyKey,
        public DateTimeImmutable $occurredAt,
        public ?string $metricKey = null,
        public ?float $value = null,
        public array $properties = [],
    ) {
        //
    }
}
