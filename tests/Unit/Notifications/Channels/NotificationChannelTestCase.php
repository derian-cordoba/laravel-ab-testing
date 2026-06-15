<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Notifications\Channels;

use ABTests\Tests\Support\TestApplication;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Base for notification channel tests. Sets up a minimal container with
 * Http::fake() and a configurable notification channel config so each channel
 * test can focus purely on behaviour rather than infrastructure.
 */
abstract class NotificationChannelTestCase extends TestCase
{
    protected HttpFactory $httpFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new TestApplication();
        Facade::setFacadeApplication($app);
        Container::setInstance($app);

        $app->instance('config', new ConfigRepository([
            'ab-testing' => [
                'notifications' => [
                    'channels' => $this->channelConfig(),
                ],
            ],
            'app' => ['name' => 'TestApp'],
        ]));

        $app->instance('log', new NullLogger());

        $this->httpFactory = new HttpFactory();
        $app->instance(HttpFactory::class, $this->httpFactory);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        parent::tearDown();
    }

    /**
     * Return the full 'channels' sub-array used for this test class's config.
     *
     * @return array<string, mixed>
     */
    abstract protected function channelConfig(): array;

    protected function makePayload(string $event = 'experiment_paused'): \ABTests\Notifications\NotificationPayload
    {
        return new \ABTests\Notifications\NotificationPayload(
            event: $event,
            title: 'Experiment paused: checkout-button-color',
            experimentKey: 'checkout-button-color',
            flagKey: null,
            data: ['actor' => 'alice', 'actor_type' => 'user'],
            occurredAt: new \DateTimeImmutable('2026-06-15T00:00:00+00:00'),
        );
    }
}
