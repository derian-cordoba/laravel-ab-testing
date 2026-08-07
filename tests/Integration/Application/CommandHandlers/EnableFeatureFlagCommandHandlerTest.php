<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Application\CommandHandlers;

use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Application\Handlers\EnableFeatureFlagCommandHandler;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use ABTests\Infrastructure\Database\DatabaseFeatureFlagRepository;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Infrastructure\Events\LaravelDomainEventDispatcher;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;

final class EnableFeatureFlagCommandHandlerTest extends DatabaseTestCase
{
    #[Test]
    public function creates_record_and_enables_when_no_state_exists(): void
    {
        (new EnableFeatureFlagCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'new-checkout');

        self::assertNotNull($model);
        self::assertTrue($model->is_enabled);
    }

    #[Test]
    public function enables_an_existing_disabled_flag(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'new-checkout',
            'is_enabled' => false,
        ]);

        (new EnableFeatureFlagCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        $model = FeatureFlagStateModel::query()->firstWhere('key', 'new-checkout');

        self::assertTrue($model->is_enabled);
    }

    #[Test]
    public function enabling_an_already_enabled_flag_is_idempotent(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'new-checkout',
            'is_enabled' => true,
        ]);

        (new EnableFeatureFlagCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        self::assertSame(1, FeatureFlagStateModel::query()->where('key', 'new-checkout')->count());
        self::assertTrue(
            FeatureFlagStateModel::query()->firstWhere('key', 'new-checkout')->is_enabled,
        );
    }

    #[Test]
    public function does_not_affect_other_flag_records(): void
    {
        FeatureFlagStateModel::query()->create([
            'key' => 'other-flag',
            'is_enabled' => false,
        ]);

        (new EnableFeatureFlagCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'tester',
        ));

        self::assertFalse(
            FeatureFlagStateModel::query()->firstWhere('key', 'other-flag')->is_enabled,
        );
    }

    #[Test]
    public function dispatches_feature_flag_enabled_event(): void
    {
        /** @var list<FeatureFlagEnabledEvent> $fired */
        $fired = [];
        Container::getInstance()->make('events')->listen(
            FeatureFlagEnabledEvent::class,
            static function (FeatureFlagEnabledEvent $event) use (&$fired): void {
                $fired[] = $event;
            },
        );

        (new EnableFeatureFlagCommandHandler(new DatabaseFeatureFlagRepository(), new LaravelDomainEventDispatcher()))->handle(new EnableFeatureFlagCommand(
            flagKey: 'new-checkout',
            actorIdentifier: 'alice',
        ));

        self::assertCount(1, $fired);
        self::assertSame('new-checkout', $fired[0]->flagKey);
        self::assertSame('alice', $fired[0]->actorIdentifier);
    }
}
