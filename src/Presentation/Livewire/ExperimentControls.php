<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Commands\ArchiveExperimentCommand;
use ABTests\Application\Commands\SetExperimentEnvironmentsCommand;
use ABTests\Infrastructure\Jobs\RefreshRollupsJob;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Application\Commands\RampTrafficCommand;
use ABTests\Application\Commands\ResumeExperimentCommand;
use ABTests\Application\Commands\StartExperimentCommand;
use ABTests\Application\Commands\StopExperimentCommand;
use ABTests\Application\Commands\ToggleKillSwitchCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Embedded Livewire component that renders the lifecycle controls for a single
 * experiment. Dispatches commands through the CommandBus, validates allowed
 * transitions before sending, and re-emits 'experiment-updated' so the parent
 * ExperimentDetail re-renders with fresh data.
 */
final class ExperimentControls extends Component
{
    public string $experimentKey = '';

    public bool $showKillSwitch = true;

    public bool $showData = true;

    public bool $showTrafficRamp = true;

    #[Validate('required|integer|min:0|max:100')]
    public int $trafficPercentage = 100;

    /** @var list<string> */
    public array $allowedEnvironments = [];

    public string $flashMessage = '';
    public string $flashType = 'success';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;

        $model = ExperimentModel::query()->firstWhere('key', $experimentKey);

        if ($model !== null) {
            $this->trafficPercentage    = $model->traffic_percentage;
            $this->allowedEnvironments  = $model->allowed_environments ?? [];
        }
    }

    public function start(): void
    {
        $this->dispatchCommand(new StartExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function pause(): void
    {
        $this->dispatchCommand(new PauseExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function resume(): void
    {
        $this->dispatchCommand(new ResumeExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function stop(): void
    {
        $this->dispatchCommand(new StopExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function archive(): void
    {
        $this->dispatchCommand(new ArchiveExperimentCommand(
            experimentKey: $this->experimentKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function toggleKillSwitch(): void
    {
        $model = ExperimentModel::query()->where('key', $this->experimentKey)->first();
        $isKilled = $model !== null && ! $model->is_killed;

        $this->dispatchCommand(new ToggleKillSwitchCommand(
            experimentKey: $this->experimentKey,
            isKilled: $isKilled,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function setEnvironments(): void
    {
        $this->dispatchCommand(new SetExperimentEnvironmentsCommand(
            experimentKey: $this->experimentKey,
            allowedEnvironments: $this->allowedEnvironments === [] ? null : array_values($this->allowedEnvironments),
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function rampTraffic(): void
    {
        $this->validateOnly('trafficPercentage');

        $this->dispatchCommand(new RampTrafficCommand(
            experimentKey: $this->experimentKey,
            trafficPercentage: $this->trafficPercentage,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    /**
     * Run the rollup job synchronously so the results table reflects the latest
     * events without waiting for the scheduler. Useful in development and for
     * running after a simulation batch.
     */
    public function refreshRollup(): void
    {
        try {
            $refreshed = (new RefreshRollupsJob())->refreshExperimentByKey(
                experimentKey: $this->experimentKey,
                registry: app(ExperimentRegistry::class),
            );

            if (! $refreshed) {
                $this->flashMessage = 'Experiment record not found. Nothing was refreshed.';
                $this->flashType    = 'error';

                return;
            }

            $this->flashMessage = 'Rollup refreshed. Results are up to date.';
            $this->flashType    = 'success';
            $this->dispatch('experiment-updated');
        } catch (Throwable $e) {
            $this->flashMessage = 'Rollup failed: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    public function render(): View
    {
        $model = ExperimentModel::query()->firstWhere('key', $this->experimentKey);
        $status = $model !== null ? ExperimentStatus::from($model->status) : null;
        $allowedTransitions = $status?->allowedTransitions() ?? [];

        return view('ab-testing::livewire.experiment-controls', compact('model', 'status', 'allowedTransitions'));
    }

    private function dispatchCommand(object $command): void
    {
        try {
            app(CommandBus::class)->dispatch($command);

            $this->flashMessage = 'Action completed successfully.';
            $this->flashType = 'success';
            $this->dispatch('experiment-updated');
        } catch (InvalidStateTransition $e) {
            $this->flashMessage = $e->getMessage();
            $this->flashType = 'error';
        } catch (Throwable $e) {
            $this->flashMessage = 'An unexpected error occurred: ' . $e->getMessage();
            $this->flashType = 'error';
        }
    }

    private function actorIdentifier(): string
    {
        $user = Auth::user();

        if ($user !== null && method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }

        return 'dashboard';
    }
}
