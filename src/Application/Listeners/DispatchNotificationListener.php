<?php

declare(strict_types=1);

namespace ABTests\Application\Listeners;

use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Domain\Events\ExperimentStartedEvent;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Notifications\Jobs\DispatchNotificationsJob;
use ABTests\Notifications\NotificationPayload;
use DateTimeImmutable;

/**
 * Converts any supported domain event into a {@see NotificationPayload} and
 * queues a {@see DispatchNotificationsJob}. Registered as a listener for every
 * event type in the service provider. Notifications are skipped entirely when
 * the global enabled flag is off, or when the individual event type is
 * disabled in config.
 */
final readonly class DispatchNotificationListener
{
    public function handle(object $event): void
    {
        if (! config('ab-testing.notifications.enabled', false)) {
            return;
        }

        $payload = $this->toPayload($event);

        if ($payload === null) {
            return;
        }

        /** @var array<string, bool> $eventConfig */
        $eventConfig = config('ab-testing.notifications.events', []);

        if (isset($eventConfig[$payload->event]) && $eventConfig[$payload->event] === false) {
            return;
        }

        DispatchNotificationsJob::dispatch($payload);
    }

    private function toPayload(object $event): ?NotificationPayload
    {
        return match (true) {
            $event instanceof ExperimentStartedEvent => new NotificationPayload(
                event: 'experiment_started',
                title: "Experiment started: $event->experimentKey",
                experimentKey: $event->experimentKey,
                flagKey: null,
                data: [
                    'actor'              => $event->actorIdentifier,
                    'actor_type'         => $event->actorType,
                    'traffic_percentage' => $event->trafficPercentage,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof ExperimentPausedEvent => new NotificationPayload(
                event: 'experiment_paused',
                title: "Experiment paused: $event->experimentKey",
                experimentKey: $event->experimentKey,
                flagKey: null,
                data: [
                    'actor'      => $event->actorIdentifier,
                    'actor_type' => $event->actorType,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof ExperimentResumedEvent => new NotificationPayload(
                event: 'experiment_resumed',
                title: "Experiment resumed: $event->experimentKey",
                experimentKey: $event->experimentKey,
                flagKey: null,
                data: [
                    'actor'      => $event->actorIdentifier,
                    'actor_type' => $event->actorType,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof ExperimentStoppedEvent => new NotificationPayload(
                event: 'experiment_stopped',
                title: "Experiment stopped: $event->experimentKey",
                experimentKey: $event->experimentKey,
                flagKey: null,
                data: [
                    'actor'      => $event->actorIdentifier,
                    'actor_type' => $event->actorType,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof FeatureFlagEnabledEvent => new NotificationPayload(
                event: 'feature_flag_enabled',
                title: "Feature flag enabled: $event->flagKey",
                experimentKey: null,
                flagKey: $event->flagKey,
                data: [
                    'actor'      => $event->actorIdentifier,
                    'actor_type' => $event->actorType,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof FeatureFlagDisabledEvent => new NotificationPayload(
                event: 'feature_flag_disabled',
                title: "Feature flag disabled: $event->flagKey",
                experimentKey: null,
                flagKey: $event->flagKey,
                data: [
                    'actor'      => $event->actorIdentifier,
                    'actor_type' => $event->actorType,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof KillSwitchActivatedEvent => new NotificationPayload(
                event: 'kill_switch_activated',
                title: $event->activated
                    ? 'Kill switch activated: ' . ($event->experimentKey ?? $event->flagKey)
                    : 'Kill switch deactivated: ' . ($event->experimentKey ?? $event->flagKey),
                experimentKey: $event->experimentKey,
                flagKey: $event->flagKey,
                data: [
                    'activated'  => $event->activated,
                    'actor'      => $event->actorIdentifier,
                    'actor_type' => $event->actorType,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            $event instanceof GuardrailBreachedEvent => new NotificationPayload(
                event: 'guardrail_breached',
                title: "Guardrail breached on: $event->experimentKey",
                experimentKey: $event->experimentKey,
                flagKey: null,
                data: [
                    'metric_key'       => $event->metricKey,
                    'variant_key'      => $event->variantKey,
                    'observed_value'   => $event->observedValue,
                    'threshold_value'  => $event->thresholdValue,
                ],
                occurredAt: new DateTimeImmutable(),
            ),

            default => null,
        };
    }
}
