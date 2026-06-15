<?php

declare(strict_types=1);

namespace ABTests\Notifications\Channels;

use ABTests\Notifications\NotificationPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a notification payload to a Slack incoming webhook URL, formatted
 * as a Block Kit message with a colour-coded sidebar attachment.
 *
 * Colour mapping:
 *   Red    — guardrail_breached, kill_switch_activated
 *   Yellow — experiment_paused, experiment_stopped, feature_flag_disabled
 *   Green  — experiment_started, experiment_resumed, feature_flag_enabled
 */
final readonly class SlackChannel
{
    private const array COLOR_MAP = [
        'guardrail_breached'      => '#e53e3e',
        'kill_switch_activated'   => '#e53e3e',
        'experiment_paused'       => '#d69e2e',
        'experiment_stopped'      => '#d69e2e',
        'feature_flag_disabled'   => '#d69e2e',
        'experiment_started'      => '#38a169',
        'experiment_resumed'      => '#38a169',
        'feature_flag_enabled'    => '#38a169',
    ];

    public function send(NotificationPayload $payload): void
    {
        /** @var array<string, mixed> $config */
        $config = config('ab-testing.notifications.channels.slack', []);

        if (empty($config['webhook_url'])) {
            Log::warning('[ABTesting] SlackChannel: no webhook_url configured; skipping.');
            return;
        }

        $color  = self::COLOR_MAP[$payload->event] ?? '#718096';
        $fields = $this->buildFields($payload);

        $message = [
            'attachments' => [
                [
                    'color'      => $color,
                    'title'      => $payload->title,
                    'fields'     => $fields,
                    'footer'     => 'A/B Testing · ' . config('app.name', 'Laravel'),
                    'ts'         => $payload->occurredAt->getTimestamp(),
                    'fallback'   => $payload->title,
                ],
            ],
        ];

        try {
            $response = Http::timeout(5)
                ->withHeader('Content-Type', 'application/json')
                ->post($config['webhook_url'], $message);

            if ($response->failed()) {
                Log::warning(
                    '[ABTesting] SlackChannel: delivery failed.',
                    ['status' => $response->status(), 'event' => $payload->event],
                );
            }
        } catch (Throwable $e) {
            Log::error(
                '[ABTesting] SlackChannel: exception during delivery.',
                ['event' => $payload->event, 'error' => $e->getMessage()],
            );
        }
    }

    /**
     * @return list<array{title: string, value: string, short: bool}>
     */
    private function buildFields(NotificationPayload $payload): array
    {
        $fields = [];

        if ($payload->experimentKey !== null) {
            $fields[] = ['title' => 'Experiment', 'value' => $payload->experimentKey, 'short' => true];
        }

        if ($payload->flagKey !== null) {
            $fields[] = ['title' => 'Feature Flag', 'value' => $payload->flagKey, 'short' => true];
        }

        foreach ($payload->data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $fields[] = [
                'title' => ucwords(str_replace('_', ' ', $key)),
                'value' => is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value,
                'short' => true,
            ];
        }

        return $fields;
    }
}
