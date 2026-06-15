<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\CommandHandlers\DisableFeatureFlagCommandHandler;
use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class DisableFeatureFlagCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function creates_record_and_disables_when_no_state_exists(): void
    {
        new DisableFeatureFlagCommandHandler()->handle(new DisableFeatureFlagCommand(
            flagKey: 'my-flag',
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'my-flag');

        self::assertNotNull($model);
        self::assertFalse($model->is_enabled);
    }

    #[Test]
    public function disables_an_existing_enabled_flag(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => true,
        ]);

        new DisableFeatureFlagCommandHandler()->handle(new DisableFeatureFlagCommand(
            flagKey: 'my-flag',
            actorIdentifier: 'tester',
        ));

        self::assertFalse(
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->is_enabled
        );
    }

    #[Test]
    public function disabling_an_already_disabled_flag_is_idempotent(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'        => 'my-flag',
            'is_enabled' => false,
        ]);

        new DisableFeatureFlagCommandHandler()->handle(new DisableFeatureFlagCommand(
            flagKey: 'my-flag',
            actorIdentifier: 'tester',
        ));

        self::assertSame(1, FeatureFlagStateModel::query()->where('key', 'my-flag')->count());
        self::assertFalse(
            FeatureFlagStateModel::query()->firstWhere('key', 'my-flag')->is_enabled
        );
    }

    #[Test]
    public function dispatches_feature_flag_disabled_event(): void
    {
        /** @var list<FeatureFlagDisabledEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            FeatureFlagDisabledEvent::class,
            static function (FeatureFlagDisabledEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        new DisableFeatureFlagCommandHandler()->handle(new DisableFeatureFlagCommand(
            flagKey: 'my-flag',
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('my-flag', $fired[0]->flagKey);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }
}
