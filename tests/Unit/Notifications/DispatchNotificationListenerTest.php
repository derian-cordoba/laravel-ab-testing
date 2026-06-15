<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Notifications;

use ABTests\Application\Listeners\DispatchNotificationListener;
use ABTests\Domain\Events\ExperimentEnvironmentsUpdatedEvent;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Domain\Events\ExperimentStartedEvent;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use ABTests\Domain\Events\FeatureFlagEnvironmentsUpdatedEvent;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Notifications\NotificationPayload;
use ABTests\Tests\Support\TestApplication;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * Tests the payload-mapping logic and config-gating behaviour of
 * DispatchNotificationListener without dispatching a real queued job.
 * The private toPayload() method is exercised via reflection.
 */
final class DispatchNotificationListenerTest extends TestCase
{
    private DispatchNotificationListener $listener;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new TestApplication();
        Facade::setFacadeApplication($app);
        Container::setInstance($app);

        $app->instance('config', new ConfigRepository([
            'ab-testing' => [
                'notifications' => [
                    'enabled' => true,
                    'events'  => [],
                ],
            ],
        ]));
        $app->instance('log', new NullLogger());

        $this->listener   = new DispatchNotificationListener();
        $this->reflection = new ReflectionClass($this->listener);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        parent::tearDown();
    }

    // ── Payload mapping ──────────────────────────────────────────────────────

    #[Test]
    public function experiment_started_event_maps_to_correct_payload(): void
    {
        $event = new ExperimentStartedEvent(
            experimentKey: 'checkout-button-color',
            actorIdentifier: 'alice',
            actorType: 'user',
            trafficPercentage: 50,
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('experiment_started', $payload->event);
        self::assertStringContainsString('checkout-button-color', $payload->title);
        self::assertSame('checkout-button-color', $payload->experimentKey);
        self::assertNull($payload->flagKey);
        self::assertSame('alice', $payload->data['actor']);
        self::assertSame(50, $payload->data['traffic_percentage']);
    }

    #[Test]
    public function experiment_paused_event_maps_to_correct_payload(): void
    {
        $event = new ExperimentPausedEvent('my-exp', 'bob', 'user');

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('experiment_paused', $payload->event);
        self::assertSame('my-exp', $payload->experimentKey);
        self::assertSame('bob', $payload->data['actor']);
    }

    #[Test]
    public function experiment_resumed_event_maps_to_correct_payload(): void
    {
        $event = new ExperimentResumedEvent('my-exp', 'carol', 'user');

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('experiment_resumed', $payload->event);
        self::assertSame('my-exp', $payload->experimentKey);
    }

    #[Test]
    public function experiment_stopped_event_maps_to_correct_payload(): void
    {
        $event = new ExperimentStoppedEvent('my-exp', 'dave', 'user');

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('experiment_stopped', $payload->event);
        self::assertSame('my-exp', $payload->experimentKey);
    }

    #[Test]
    public function feature_flag_enabled_event_maps_to_correct_payload(): void
    {
        $event = new FeatureFlagEnabledEvent('new-checkout', 'alice', 'user');

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('feature_flag_enabled', $payload->event);
        self::assertNull($payload->experimentKey);
        self::assertSame('new-checkout', $payload->flagKey);
    }

    #[Test]
    public function feature_flag_disabled_event_maps_to_correct_payload(): void
    {
        $event = new FeatureFlagDisabledEvent('new-checkout', 'alice', 'user');

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('feature_flag_disabled', $payload->event);
        self::assertSame('new-checkout', $payload->flagKey);
    }

    #[Test]
    public function kill_switch_activated_for_experiment_maps_correctly(): void
    {
        $event = new KillSwitchActivatedEvent(
            experimentKey: 'my-exp',
            flagKey: null,
            activated: true,
            actorIdentifier: 'alice',
            actorType: 'user',
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('kill_switch_activated', $payload->event);
        self::assertStringContainsString('activated', $payload->title);
        self::assertSame('my-exp', $payload->experimentKey);
        self::assertTrue($payload->data['activated']);
    }

    #[Test]
    public function kill_switch_deactivated_title_reflects_state(): void
    {
        $event = new KillSwitchActivatedEvent(
            experimentKey: 'my-exp',
            flagKey: null,
            activated: false,
            actorIdentifier: 'alice',
            actorType: 'user',
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertStringContainsString('deactivated', $payload->title);
        self::assertFalse($payload->data['activated']);
    }

    #[Test]
    public function kill_switch_for_flag_uses_flag_key_in_title(): void
    {
        $event = new KillSwitchActivatedEvent(
            experimentKey: null,
            flagKey: 'my-flag',
            activated: true,
            actorIdentifier: 'alice',
            actorType: 'user',
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertStringContainsString('my-flag', $payload->title);
        self::assertNull($payload->experimentKey);
        self::assertSame('my-flag', $payload->flagKey);
    }

    #[Test]
    public function experiment_environments_updated_event_maps_to_correct_payload(): void
    {
        $event = new ExperimentEnvironmentsUpdatedEvent(
            experimentKey: 'my-exp',
            allowedEnvironments: ['production', 'staging'],
            actorIdentifier: 'alice',
            actorType: 'user',
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('experiment_environments_updated', $payload->event);
        self::assertStringContainsString('my-exp', $payload->title);
        self::assertSame('my-exp', $payload->experimentKey);
        self::assertNull($payload->flagKey);
        self::assertSame('alice', $payload->data['actor']);
        self::assertSame(['production', 'staging'], $payload->data['allowed_environments']);
    }

    #[Test]
    public function experiment_environments_updated_event_with_null_shows_all_in_payload(): void
    {
        $event = new ExperimentEnvironmentsUpdatedEvent(
            experimentKey: 'my-exp',
            allowedEnvironments: null,
            actorIdentifier: 'alice',
            actorType: 'user',
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('all', $payload->data['allowed_environments']);
    }

    #[Test]
    public function feature_flag_environments_updated_event_maps_to_correct_payload(): void
    {
        $event = new FeatureFlagEnvironmentsUpdatedEvent(
            flagKey: 'my-flag',
            allowedEnvironments: ['local'],
            actorIdentifier: 'bob',
            actorType: 'user',
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('feature_flag_environments_updated', $payload->event);
        self::assertStringContainsString('my-flag', $payload->title);
        self::assertNull($payload->experimentKey);
        self::assertSame('my-flag', $payload->flagKey);
        self::assertSame('bob', $payload->data['actor']);
        self::assertSame(['local'], $payload->data['allowed_environments']);
    }

    #[Test]
    public function guardrail_breached_event_maps_to_correct_payload(): void
    {
        $event = new GuardrailBreachedEvent(
            experimentKey: 'my-exp',
            metricKey: 'error-rate',
            variantKey: 'treatment',
            observedValue: 0.023,
            thresholdValue: 0.005,
        );

        $payload = $this->toPayload($event);

        self::assertNotNull($payload);
        self::assertSame('guardrail_breached', $payload->event);
        self::assertSame('my-exp', $payload->experimentKey);
        self::assertSame('error-rate', $payload->data['metric_key']);
        self::assertSame('treatment', $payload->data['variant_key']);
        self::assertSame(0.023, $payload->data['observed_value']);
        self::assertSame(0.005, $payload->data['threshold_value']);
    }

    #[Test]
    public function unknown_event_object_returns_null_payload(): void
    {
        $unknownEvent = new \stdClass();

        $payload = $this->toPayload($unknownEvent);

        self::assertNull($payload);
    }

    // ── Config gating ────────────────────────────────────────────────────────

    #[Test]
    public function handle_returns_early_when_notifications_are_globally_disabled(): void
    {
        Container::getInstance()->make('config')
            ->set('ab-testing.notifications.enabled', false);

        // If the listener does not return early it will try to dispatch a job,
        // which would throw because no Bus is bound. The absence of an exception
        // confirms the early return path is taken.
        $this->listener->handle(new ExperimentPausedEvent('exp', 'alice', 'user'));

        $this->addToAssertionCount(1);
    }

    #[Test]
    #[DataProvider('disabledEventProvider')]
    public function handle_returns_early_when_specific_event_type_is_disabled(string $eventKey): void
    {
        Container::getInstance()->make('config')
            ->set("ab-testing.notifications.events.{$eventKey}", false);

        $event = match ($eventKey) {
            'experiment_started'                => new ExperimentStartedEvent('e', 'a', 'user', 100),
            'experiment_paused'                 => new ExperimentPausedEvent('e', 'a', 'user'),
            'experiment_resumed'                => new ExperimentResumedEvent('e', 'a', 'user'),
            'experiment_stopped'                => new ExperimentStoppedEvent('e', 'a', 'user'),
            'experiment_environments_updated'   => new ExperimentEnvironmentsUpdatedEvent('e', ['production'], 'a', 'user'),
            'feature_flag_enabled'              => new FeatureFlagEnabledEvent('f', 'a', 'user'),
            'feature_flag_disabled'             => new FeatureFlagDisabledEvent('f', 'a', 'user'),
            'feature_flag_environments_updated' => new FeatureFlagEnvironmentsUpdatedEvent('f', ['production'], 'a', 'user'),
            'kill_switch_activated'             => new KillSwitchActivatedEvent('e', null, true, 'a', 'user'),
            'guardrail_breached'                => new GuardrailBreachedEvent('e', 'm', 'v', 0.1, 0.05),
        };

        $this->listener->handle($event);

        $this->addToAssertionCount(1);
    }

    /** @return array<string, array{string}> */
    public static function disabledEventProvider(): array
    {
        return [
            'experiment_started'                => ['experiment_started'],
            'experiment_paused'                 => ['experiment_paused'],
            'experiment_resumed'                => ['experiment_resumed'],
            'experiment_stopped'                => ['experiment_stopped'],
            'experiment_environments_updated'   => ['experiment_environments_updated'],
            'feature_flag_enabled'              => ['feature_flag_enabled'],
            'feature_flag_disabled'             => ['feature_flag_disabled'],
            'feature_flag_environments_updated' => ['feature_flag_environments_updated'],
            'kill_switch_activated'             => ['kill_switch_activated'],
            'guardrail_breached'                => ['guardrail_breached'],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function toPayload(object $event): ?NotificationPayload
    {
        $method = $this->reflection->getMethod('toPayload');

        return $method->invoke($this->listener, $event);
    }
}
