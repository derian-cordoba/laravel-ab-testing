<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Notifications;

use ABTests\Notifications\NotificationPayload;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NotificationPayloadTest extends TestCase
{
    #[Test]
    public function to_array_contains_all_fields(): void
    {
        $occurredAt = new DateTimeImmutable('2026-01-15T12:00:00+00:00');

        $payload = new NotificationPayload(
            event: 'experiment_paused',
            title: 'Experiment paused: checkout-button-color',
            experimentKey: 'checkout-button-color',
            flagKey: null,
            data: ['actor' => 'alice', 'actor_type' => 'user'],
            occurredAt: $occurredAt,
        );

        $array = $payload->toArray();

        self::assertSame('experiment_paused', $array['event']);
        self::assertSame('Experiment paused: checkout-button-color', $array['title']);
        self::assertSame('checkout-button-color', $array['experiment_key']);
        self::assertNull($array['flag_key']);
        self::assertSame(['actor' => 'alice', 'actor_type' => 'user'], $array['data']);
        self::assertSame('2026-01-15T12:00:00+00:00', $array['occurred_at']);
    }

    #[Test]
    public function to_array_includes_flag_key_for_flag_events(): void
    {
        $payload = new NotificationPayload(
            event: 'feature_flag_enabled',
            title: 'Feature flag enabled: new-checkout',
            experimentKey: null,
            flagKey: 'new-checkout',
            data: ['actor' => 'bob'],
            occurredAt: new DateTimeImmutable(),
        );

        $array = $payload->toArray();

        self::assertNull($array['experiment_key']);
        self::assertSame('new-checkout', $array['flag_key']);
    }

    #[Test]
    public function to_array_occurred_at_is_iso8601(): void
    {
        $payload = new NotificationPayload(
            event: 'guardrail_breached',
            title: 'Guardrail breached',
            experimentKey: 'test',
            flagKey: null,
            data: [],
            occurredAt: new DateTimeImmutable('2026-06-15T00:00:00+00:00'),
        );

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $payload->toArray()['occurred_at'],
        );
    }

    #[Test]
    public function data_is_preserved_exactly(): void
    {
        $data = [
            'metric_key'      => 'error-rate',
            'variant_key'     => 'treatment',
            'observed_value'  => 0.023,
            'threshold_value' => 0.005,
        ];

        $payload = new NotificationPayload(
            event: 'guardrail_breached',
            title: 'Guardrail breached on: my-experiment',
            experimentKey: 'my-experiment',
            flagKey: null,
            data: $data,
            occurredAt: new DateTimeImmutable(),
        );

        self::assertSame($data, $payload->toArray()['data']);
    }
}
