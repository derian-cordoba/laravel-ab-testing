<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Commands\CreateExperimentCommand;
use ABTests\Contracts\CommandBus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for creating a new runtime-defined experiment.
 * After successful creation, redirects to the experiment's detail page so the
 * user can add variants via the ExperimentVariantManager.
 */
final class CreateExperiment extends Component
{
    #[Validate('required|regex:/^[a-z0-9]+(-[a-z0-9]+)*$/|max:128')]
    public string $key = '';

    #[Validate('nullable|string|max:255')]
    public ?string $name = null;

    #[Validate('nullable|regex:/^[a-z0-9]+(-[a-z0-9]+)*$/|max:128')]
    public ?string $layer = null;

    #[Validate('required|integer|min:0|max:100')]
    public int $trafficPercentage = 0;

    public string $flashMessage = '';
    public string $flashType = 'error';

    public function save(): void
    {
        $this->validate();

        $normalizedKey  = strtolower(trim($this->key));
        $normalizedName = $this->name !== null && trim($this->name) !== '' ? trim($this->name) : null;
        $normalizedLayer = $this->layer !== null && trim($this->layer) !== '' ? trim($this->layer) : null;

        try {
            app(CommandBus::class)->dispatch(new CreateExperimentCommand(
                key:               $normalizedKey,
                name:              $normalizedName,
                layer:             $normalizedLayer,
                trafficPercentage: $this->trafficPercentage,
                actorIdentifier:   $this->actorIdentifier(),
            ));

            $this->redirect(route('ab-testing.experiments.show', $normalizedKey), navigate: true);
        } catch (Throwable $e) {
            $this->flashMessage = 'Failed to create experiment: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    public function render(): View
    {
        return view('ab-testing::livewire.create-experiment')
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — New Experiment']);
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
