<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Commands\UpdateExperimentCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Embedded Livewire component that renders a settings form for an experiment's
 * editable metadata fields. Shown inside ExperimentDetail. The layer field is
 * read-only once the experiment moves past draft/scheduled status.
 */
final class EditExperiment extends Component
{
    public string $experimentKey = '';

    #[Validate('nullable|string|max:255')]
    public ?string $name = null;

    #[Validate('nullable|regex:/^[a-z0-9]+(-[a-z0-9]+)*$/|max:128')]
    public ?string $layer = null;

    #[Validate('nullable|integer|min:1')]
    public ?int $targetSampleSize = null;

    public bool $layerLocked = false;

    public string $flashMessage = '';
    public string $flashType = 'success';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
        $this->loadFromModel();
    }

    #[On('experiment-updated')]
    public function onExperimentUpdated(): void
    {
        $this->loadFromModel();
    }

    public function save(): void
    {
        $this->validate();

        $normalizedName   = $this->name !== null && trim($this->name) !== '' ? trim($this->name) : null;
        $normalizedLayer  = $this->layer !== null && trim($this->layer) !== '' ? trim($this->layer) : null;

        try {
            app(CommandBus::class)->dispatch(new UpdateExperimentCommand(
                experimentKey:   $this->experimentKey,
                name:            $normalizedName,
                layer:           $normalizedLayer,
                targetSampleSize: $this->targetSampleSize,
                actorIdentifier: $this->actorIdentifier(),
            ));

            $this->flashMessage = 'Settings saved.';
            $this->flashType    = 'success';
            $this->dispatch('experiment-updated');
        } catch (Throwable $e) {
            $this->flashMessage = 'Failed to save: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    public function render(): View
    {
        return view('ab-testing::livewire.edit-experiment');
    }

    private function loadFromModel(): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $this->experimentKey);

        if ($model === null) {
            return;
        }

        $this->name             = $model->name;
        $this->layer            = $model->layer;
        $this->targetSampleSize = $model->target_sample_size;

        $editableStatuses = [ExperimentStatus::draft->value, ExperimentStatus::scheduled->value];
        $this->layerLocked = ! in_array($model->status, $editableStatuses, strict: true);
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
