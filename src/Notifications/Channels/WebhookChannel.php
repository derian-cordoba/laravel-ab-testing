<?php

declare(strict_types=1);

namespace ABTests\Notifications\Channels;

use ABTests\Notifications\NotificationPayload;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers a notification payload as an HMAC-SHA256-signed HTTP POST to a
 * configured URL. The receiver can verify authenticity by computing
 * HMAC-SHA256(secret, raw_body) and comparing against the signature header.
 *
 * Headers sent with every request:
 *
 *   X-ABTesting-Event      — snake-case event name (e.g. 'experiment_started')
 *   X-ABTesting-Timestamp  — Unix timestamp of the request (for replay protection)
 *   X-ABTesting-Signature  — 'sha256=' + hex(HMAC-SHA256(secret, body))
 */
final readonly class WebhookChannel
{
    public function send(NotificationPayload $payload): void
    {
        /** @var array<string, mixed> $config */
        $config = config('ab-testing.notifications.channels.webhook', []);

        if (empty($config['url'])) {
            Log::warning('[ABTesting] WebhookChannel: no URL configured; skipping.');
            return;
        }

        $body      = json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) time();
        $secret    = (string) ($config['secret'] ?? '');
        $signature = 'sha256=' . hash_hmac('sha256', $body, $secret);
        $timeout   = (int) ($config['timeout'] ?? 5);

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'Content-Type'         => 'application/json',
                    'X-ABTesting-Event'    => $payload->event,
                    'X-ABTesting-Timestamp'=> $timestamp,
                    'X-ABTesting-Signature'=> $signature,
                ])
                ->post($config['url'], $payload->toArray());

            if ($response->failed()) {
                Log::warning(
                    '[ABTesting] WebhookChannel: delivery failed.',
                    ['status' => $response->status(), 'event' => $payload->event, 'url' => $config['url']],
                );
            }
        } catch (Throwable $e) {
            Log::error(
                '[ABTesting] WebhookChannel: exception during delivery.',
                ['event' => $payload->event, 'url' => $config['url'], 'error' => $e->getMessage()],
            );
        }
    }
}
