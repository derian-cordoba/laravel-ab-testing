<?php

declare(strict_types=1);

namespace ABTests\Notifications;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Immutable representation of a notification to be delivered to one or more
 * channels. Built from a domain event and carried through to each channel
 * implementation. All channel-specific formatters read from this object rather
 * than from the original event so that channels are fully decoupled from the
 * domain layer.
 */
final readonly class NotificationPayload
{
    /**
     * @param string               $event         Snake-case event identifier, e.g. 'experiment_started'.
     * @param string               $title         Short human-readable summary.
     * @param string|null          $experimentKey Populated for experiment-related events.
     * @param string|null          $flagKey       Populated for feature flag-related events.
     * @param array<string, mixed> $data          Event-specific context (actor, metric values, etc.).
     * @param DateTimeImmutable    $occurredAt    When the underlying action happened.
     */
    public function __construct(
        public string $event,
        public string $title,
        public ?string $experimentKey,
        public ?string $flagKey,
        public array $data,
        public DateTimeImmutable $occurredAt,
    ) {
        //
    }

    /**
     * Serialize to an array suitable for JSON encoding in webhook or mail bodies.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event'          => $this->event,
            'title'          => $this->title,
            'experiment_key' => $this->experimentKey,
            'flag_key'       => $this->flagKey,
            'data'           => $this->data,
            'occurred_at'    => $this->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
