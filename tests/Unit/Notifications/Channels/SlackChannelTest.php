<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Notifications\Channels;

use ABTests\Notifications\Channels\SlackChannel;
use ABTests\Notifications\NotificationPayload;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SlackChannelTest extends NotificationChannelTestCase
{
    protected function channelConfig(): array
    {
        return [
            'slack' => [
                'webhook_url' => 'https://hooks.slack.com/services/test',
                'enabled'     => true,
            ],
        ];
    }

    #[Test]
    public function sends_post_to_configured_slack_webhook_url(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        (new SlackChannel())->send($this->makePayload());

        Http::assertSent(
            fn($request) => $request->url() === 'https://hooks.slack.com/services/test',
        );
    }

    #[Test]
    public function message_has_attachments_key(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        (new SlackChannel())->send($this->makePayload());

        Http::assertSent(fn($request) => isset($request->data()['attachments']));
    }

    #[Test]
    public function attachment_title_matches_payload_title(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        (new SlackChannel())->send($this->makePayload());

        Http::assertSent(function ($request) {
            return $request->data()['attachments'][0]['title'] === 'Experiment paused: checkout-button-color';
        });
    }

    #[Test]
    #[DataProvider('colorProvider')]
    public function attachment_color_matches_event_type(string $event, string $expectedColor): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $payload = new NotificationPayload(
            event: $event,
            title: "Test: {$event}",
            experimentKey: 'test-exp',
            flagKey: null,
            data: [],
            occurredAt: new DateTimeImmutable(),
        );

        (new SlackChannel())->send($payload);

        Http::assertSent(
            fn($request) => $request->data()['attachments'][0]['color'] === $expectedColor,
        );
    }

    /** @return array<string, array{string, string}> */
    public static function colorProvider(): array
    {
        return [
            'guardrail_breached'    => ['guardrail_breached',    '#e53e3e'],
            'kill_switch_activated' => ['kill_switch_activated', '#e53e3e'],
            'experiment_paused'     => ['experiment_paused',     '#d69e2e'],
            'experiment_stopped'    => ['experiment_stopped',    '#d69e2e'],
            'feature_flag_disabled' => ['feature_flag_disabled', '#d69e2e'],
            'experiment_started'    => ['experiment_started',    '#38a169'],
            'experiment_resumed'    => ['experiment_resumed',    '#38a169'],
            'feature_flag_enabled'  => ['feature_flag_enabled',  '#38a169'],
        ];
    }

    #[Test]
    public function attachment_fields_include_experiment_key(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        (new SlackChannel())->send($this->makePayload());

        Http::assertSent(function ($request) {
            $fields = $request->data()['attachments'][0]['fields'];
            $titles = array_column($fields, 'title');

            return in_array('Experiment', $titles, true);
        });
    }

    #[Test]
    public function attachment_fields_include_data_entries(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $payload = new NotificationPayload(
            event: 'experiment_paused',
            title: 'Experiment paused: test',
            experimentKey: 'test',
            flagKey: null,
            data: ['actor' => 'alice', 'actor_type' => 'user'],
            occurredAt: new DateTimeImmutable(),
        );

        (new SlackChannel())->send($payload);

        Http::assertSent(function ($request) {
            $fields = $request->data()['attachments'][0]['fields'];
            $titles = array_column($fields, 'title');

            return in_array('Actor', $titles, true);
        });
    }

    #[Test]
    public function attachment_includes_footer_with_app_name(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        (new SlackChannel())->send($this->makePayload());

        Http::assertSent(function ($request) {
            $footer = $request->data()['attachments'][0]['footer'];

            return str_contains($footer, 'A/B Testing');
        });
    }

    #[Test]
    public function skips_silently_when_webhook_url_is_not_configured(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        \Illuminate\Container\Container::getInstance()
            ->make('config')
            ->set('ab-testing.notifications.channels.slack.webhook_url', '');

        (new SlackChannel())->send($this->makePayload());

        Http::assertNothingSent();
    }

    #[Test]
    public function does_not_throw_when_slack_returns_error(): void
    {
        Http::fake(['*' => Http::response('invalid_payload', 400)]);

        (new SlackChannel())->send($this->makePayload());

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function flag_key_appears_in_fields_for_flag_events(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $payload = new NotificationPayload(
            event: 'feature_flag_enabled',
            title: 'Feature flag enabled: new-checkout',
            experimentKey: null,
            flagKey: 'new-checkout',
            data: ['actor' => 'bob'],
            occurredAt: new DateTimeImmutable(),
        );

        (new SlackChannel())->send($payload);

        Http::assertSent(function ($request) {
            $fields = $request->data()['attachments'][0]['fields'];
            $titles = array_column($fields, 'title');

            return in_array('Feature Flag', $titles, true);
        });
    }
}
