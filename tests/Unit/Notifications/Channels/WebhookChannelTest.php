<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Notifications\Channels;

use ABTests\Notifications\Channels\WebhookChannel;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

final class WebhookChannelTest extends NotificationChannelTestCase
{
    protected function channelConfig(): array
    {
        return [
            'webhook' => [
                'url'     => 'https://example.com/webhook',
                'secret'  => 'test-secret',
                'timeout' => 5,
                'enabled' => true,
            ],
        ];
    }

    #[Test]
    public function sends_post_to_configured_url(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        (new WebhookChannel())->send($this->makePayload());

        Http::assertSent(fn($request) => $request->url() === 'https://example.com/webhook');
    }

    #[Test]
    public function request_uses_json_content_type(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new WebhookChannel())->send($this->makePayload());

        Http::assertSent(fn($request) => $request->header('Content-Type')[0] === 'application/json');
    }

    #[Test]
    public function request_includes_event_name_header(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new WebhookChannel())->send($this->makePayload('experiment_paused'));

        Http::assertSent(
            fn($request) => $request->header('X-ABTesting-Event')[0] === 'experiment_paused',
        );
    }

    #[Test]
    public function request_includes_timestamp_header(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $before = time();
        (new WebhookChannel())->send($this->makePayload());
        $after = time();

        Http::assertSent(function ($request) use ($before, $after) {
            $ts = (int) $request->header('X-ABTesting-Timestamp')[0];

            return $ts >= $before && $ts <= $after;
        });
    }

    #[Test]
    public function request_includes_hmac_sha256_signature(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        (new WebhookChannel())->send($this->makePayload());

        Http::assertSent(function ($request) {
            $signature = $request->header('X-ABTesting-Signature')[0] ?? '';

            return str_starts_with($signature, 'sha256=')
                && strlen($signature) === strlen('sha256=') + 64; // hex(sha256) = 64 chars
        });
    }

    #[Test]
    public function signature_is_valid_hmac_of_body_with_configured_secret(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $payload = $this->makePayload();
        (new WebhookChannel())->send($payload);

        Http::assertSent(function ($request) use ($payload) {
            $expectedBody = json_encode($payload->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $expectedSig  = 'sha256=' . hash_hmac('sha256', $expectedBody, 'test-secret');

            return $request->header('X-ABTesting-Signature')[0] === $expectedSig;
        });
    }

    #[Test]
    public function body_contains_all_payload_fields(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        $payload = $this->makePayload('experiment_paused');
        (new WebhookChannel())->send($payload);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['event'] === 'experiment_paused'
                && $body['experiment_key'] === 'checkout-button-color'
                && isset($body['occurred_at'])
                && isset($body['data']['actor']);
        });
    }

    #[Test]
    public function skips_silently_when_url_is_not_configured(): void
    {
        Http::fake(['*' => Http::response([], 200)]);

        // Override config so URL is empty.
        \Illuminate\Container\Container::getInstance()
            ->make('config')
            ->set('ab-testing.notifications.channels.webhook.url', '');

        (new WebhookChannel())->send($this->makePayload());

        Http::assertNothingSent();
    }

    #[Test]
    public function does_not_throw_when_endpoint_returns_non_200(): void
    {
        Http::fake(['*' => Http::response([], 500)]);

        // Must not throw; failures are logged, not propagated.
        (new WebhookChannel())->send($this->makePayload());

        $this->addToAssertionCount(1);
    }
}
