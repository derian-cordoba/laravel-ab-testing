<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution;

use ABTests\Contracts\Bucketable;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\Variant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Enums\EventType;
use ABTests\Values\RecordedEvent;
use DateTimeImmutable;

/**
 * Records an exposure event for a given unit/variant/experiment triple.
 *
 * Extracted from ExperimentResolver so that exposure recording is testable in
 * isolation and so the resolver's constructor expresses its intent more
 * clearly — it resolves variants, it does not know how events are formatted.
 *
 * Idempotency key format: "exposure:{experimentKey}:{unitKey}"
 * The EventSink's unique constraint on this key ensures that duplicate
 * expose() calls within the same request are deduplicated.
 */
final class ExposureRecorder
{
    public function __construct(private readonly EventSink $eventSink) {}

    public function record(ExperimentDefinition $definition, Bucketable $unit, Variant $variant): void
    {
        $this->eventSink->record(new RecordedEvent(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: $unit->bucketingKey(),
            variantKey: $variant->key(),
            type: EventType::exposure,
            idempotencyKey: "exposure:{$definition->key}:{$unit->bucketingKey()}",
            occurredAt: new DateTimeImmutable(),
        ));
    }
}
