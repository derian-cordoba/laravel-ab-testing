<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Notifications\Channels;

use ABTests\Infrastructure\Notifications\Channels\MailChannel;
use ABTests\Infrastructure\Notifications\NotificationPayload;
use ABTests\Tests\Support\TestApplication;
use DateTimeImmutable;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class MailChannelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new TestApplication();
        Facade::setFacadeApplication($app);
        Container::setInstance($app);

        $app->instance('config', new ConfigRepository([
            'ab-testing' => [
                'notifications' => [
                    'channels' => [
                        'mail' => [
                            'enabled'    => true,
                            'recipients' => ['dev@example.com'],
                        ],
                    ],
                ],
            ],
            'app'  => ['name' => 'TestApp'],
            'mail' => [
                'default'  => 'array',
                'mailers'  => ['array' => ['transport' => 'array']],
                'from'     => ['address' => 'no-reply@example.com', 'name' => 'TestApp'],
            ],
        ]));

        $app->instance('log', new NullLogger());
        $app->instance(HttpFactory::class, new HttpFactory());
        $app->singleton('mail.manager', fn($a) => new MailManager($a));
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        parent::tearDown();
    }

    #[Test]
    public function sends_email_to_configured_recipients(): void
    {
        Mail::fake();

        (new MailChannel())->send($this->makePayload());

        Mail::assertSentCount(1);
    }

    #[Test]
    public function skips_silently_when_no_recipients_configured(): void
    {
        Mail::fake();

        Container::getInstance()
            ->make('config')
            ->set('ab-testing.notifications.channels.mail.recipients', []);

        (new MailChannel())->send($this->makePayload());

        Mail::assertNothingSent();
    }

    #[Test]
    public function html_output_contains_event_title(): void
    {
        $payload = $this->makePayload('experiment_paused');

        $channel    = new MailChannel();
        $reflection = new \ReflectionClass($channel);
        $method     = $reflection->getMethod('buildHtml');

        $html = $method->invoke($channel, $payload);

        self::assertStringContainsString('Experiment paused: checkout-button-color', $html);
    }

    #[Test]
    public function html_output_contains_experiment_key(): void
    {
        $payload    = $this->makePayload();
        $channel    = new MailChannel();
        $reflection = new \ReflectionClass($channel);
        $method     = $reflection->getMethod('buildHtml');

        $html = $method->invoke($channel, $payload);

        self::assertStringContainsString('checkout-button-color', $html);
        self::assertStringContainsString('Experiment', $html);
    }

    #[Test]
    public function html_output_contains_actor_from_data(): void
    {
        $payload    = $this->makePayload();
        $channel    = new MailChannel();
        $reflection = new \ReflectionClass($channel);
        $method     = $reflection->getMethod('buildHtml');

        $html = $method->invoke($channel, $payload);

        self::assertStringContainsString('alice', $html);
    }

    #[Test]
    public function html_output_contains_occurred_at(): void
    {
        $payload    = $this->makePayload();
        $channel    = new MailChannel();
        $reflection = new \ReflectionClass($channel);
        $method     = $reflection->getMethod('buildHtml');

        $html = $method->invoke($channel, $payload);

        self::assertStringContainsString('2026-06-15', $html);
        self::assertStringContainsString('Occurred At', $html);
    }

    #[Test]
    public function html_output_is_valid_html_document(): void
    {
        $payload    = $this->makePayload();
        $channel    = new MailChannel();
        $reflection = new \ReflectionClass($channel);
        $method     = $reflection->getMethod('buildHtml');

        $html = $method->invoke($channel, $payload);

        self::assertStringContainsString('<!DOCTYPE html>', $html);
        self::assertStringContainsString('</html>', $html);
    }

    private function makePayload(string $event = 'experiment_paused'): NotificationPayload
    {
        return new NotificationPayload(
            event: $event,
            title: 'Experiment paused: checkout-button-color',
            experimentKey: 'checkout-button-color',
            flagKey: null,
            data: ['actor' => 'alice', 'actor_type' => 'user'],
            occurredAt: new DateTimeImmutable('2026-06-15T00:00:00+00:00'),
        );
    }
}
