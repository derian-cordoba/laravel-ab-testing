<?php

declare(strict_types=1);

namespace ABTests\Application\Listeners;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Enums\ExperimentStatus;

/**
 * Automatically pauses an experiment when a guardrail metric breaches its
 * allowed regression threshold. Only pauses if the experiment is still running
 * (it may have already been paused by a concurrent breach or manual action).
 */
final readonly class AutoPauseOnGuardrailBreachListener
{
    public function __construct(
        private CommandBus $commandBus,
        private ExperimentRepository $experimentRepository,
    ) {
        //
    }

    public function handle(GuardrailBreachedEvent $event): void
    {
        $model = $this->experimentRepository->findByKey($event->experimentKey);

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
