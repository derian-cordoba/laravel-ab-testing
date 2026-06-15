<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\CommandHandlers\ToggleKillSwitchCommandHandler;
use ABTests\Application\Commands\ToggleKillSwitchCommand;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class ToggleKillSwitchCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function activating_kill_switch_sets_is_killed_and_killed_at(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'my-exp',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed'          => false,
            'killed_at'          => null,
        ]);

        new ToggleKillSwitchCommandHandler()->handle(new ToggleKillSwitchCommand(
            experimentKey: 'my-exp',
            isKilled: true,
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertTrue((bool) $model->is_killed);
        self::assertNotNull($model->killed_at);
    }

    #[Test]
    public function deactivating_kill_switch_clears_is_killed_and_killed_at(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'my-exp',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed'          => true,
            'killed_at'          => \Carbon\Carbon::now(),
        ]);

        new ToggleKillSwitchCommandHandler()->handle(new ToggleKillSwitchCommand(
            experimentKey: 'my-exp',
            isKilled: false,
            actorIdentifier: 'tester',
        ));

        $model = ExperimentModel::query()->firstWhere('key', 'my-exp');

        self::assertFalse((bool) $model->is_killed);
        self::assertNull($model->killed_at);
    }

    #[Test]
    public function throws_when_experiment_does_not_exist(): void
    {
        $this->expectException(ExperimentNotFound::class);

        new ToggleKillSwitchCommandHandler()->handle(new ToggleKillSwitchCommand(
            experimentKey: 'nonexistent',
            isKilled: true,
            actorIdentifier: 'tester',
        ));
    }

    #[Test]
    public function dispatches_kill_switch_activated_event_with_experiment_key(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'my-exp',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed'          => false,
            'killed_at'          => null,
        ]);

        /** @var list<KillSwitchActivatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            KillSwitchActivatedEvent::class,
            static function (KillSwitchActivatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        new ToggleKillSwitchCommandHandler()->handle(new ToggleKillSwitchCommand(
            experimentKey: 'my-exp',
            isKilled: true,
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-exp', $fired[0]->experimentKey);
        self::assertNull($fired[0]->flagKey);
        self::assertTrue($fired[0]->activated);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }

    #[Test]
    public function dispatches_kill_switch_activated_event_with_activated_false_when_deactivating(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'my-exp',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed'          => true,
            'killed_at'          => \Carbon\Carbon::now(),
        ]);

        /** @var list<KillSwitchActivatedEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            KillSwitchActivatedEvent::class,
            static function (KillSwitchActivatedEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        new ToggleKillSwitchCommandHandler()->handle(new ToggleKillSwitchCommand(
            experimentKey: 'my-exp',
            isKilled: false,
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertFalse($fired[0]->activated);
    }
}
