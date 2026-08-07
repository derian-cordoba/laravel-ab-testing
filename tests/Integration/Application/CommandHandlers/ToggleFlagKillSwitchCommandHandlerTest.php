<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Application\Handlers\ToggleFlagKillSwitchCommandHandler;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Infrastructure\Database\DatabaseFeatureFlagRepository;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Infrastructure\Events\LaravelDomainEventDispatcher;
use ABTests\Tests\Integration\DatabaseTestCase;
use Carbon\Carbon;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class ToggleFlagKillSwitchCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function activating_the_kill_switch_sets_killed_at_to_a_timestamp(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'killed_at' => null,
        ]);

        (new ToggleFlagKillSwitchCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNotNull($model->killed_at);
    }

    #[Test]
    public function deactivating_the_kill_switch_clears_killed_at(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'killed_at' => Carbon::now(),
        ]);

        (new ToggleFlagKillSwitchCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: false,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNull($model->killed_at);
    }

    #[Test]
    public function creates_record_when_no_state_exists_and_kill_switch_is_activated(): void
    {
        (new ToggleFlagKillSwitchCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'brand-new-flag',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'brand-new-flag');

        self::assertNotNull($model);
        self::assertNotNull($model->killed_at);
    }

    #[Test]
    public function does_not_change_is_enabled_when_toggling_kill_switch(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'killed_at' => null,
        ]);

        (new ToggleFlagKillSwitchCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        self::assertTrue(
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->is_enabled,
        );
    }

    #[Test]
    public function dispatches_kill_switch_activated_event_with_flag_key(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'killed_at' => null,
        ]);

        /** @var list<KillSwitchActivatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            KillSwitchActivatedEvent::class,
            static function (KillSwitchActivatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new ToggleFlagKillSwitchCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: true,
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertNull($fired[0]->experimentKey);
        self::assertSame('my-flag', $fired[0]->flagKey);
        self::assertTrue($fired[0]->activated);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }

    #[Test]
    public function dispatches_kill_switch_activated_event_with_activated_false_when_deactivating(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'my-flag',
            'is_enabled' => true,
            'killed_at' => Carbon::now(),
        ]);

        /** @var list<KillSwitchActivatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            KillSwitchActivatedEvent::class,
            static function (KillSwitchActivatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new ToggleFlagKillSwitchCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new ToggleFlagKillSwitchCommand(
            flagKey: 'my-flag',
            isKilled: false,
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertFalse($fired[0]->activated);
    }
}
