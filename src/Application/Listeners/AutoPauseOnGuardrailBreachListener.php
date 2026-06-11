<?php

declare(strict_types=1);

namespace ABTests\Application\Listeners;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;

/**
 * Automatically pauses an experiment when a guardrail metric breaches its
 * allowed regression threshold. Only pauses if the experiment is still running
 * (it may have already been paused by a concurrent breach or manual action).
 */
final readonly class AutoPauseOnGuardrailBreachListener
{
    public function __construct(private CommandBus $commandBus)
    {
        //
    }

    public function handle(GuardrailBreachedEvent $event): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $event->experimentKey);

        if ($model === null || $model->status !== ExperimentStatus::running->value) {
            return;
        }

        $this->commandBus->dispatch(new PauseExperimentCommand(
            experimentKey: $event->experimentKey,
            actorIdentifier: 'system',
            actorType: 'system',
        ));
    }
}
