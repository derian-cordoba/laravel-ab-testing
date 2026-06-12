<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Application\Commands\CreateFeatureFlagCommand;
use ABTests\Contracts\CommandBus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Full-page Livewire component for creating a new feature flag. After successful
 * creation, redirects to the flag's detail page where FeatureFlagControls
 * handles ongoing operational management.
 */
final class CreateFeatureFlag extends Component
{
    #[Validate('required|regex:/^[a-z0-9]+(-[a-z0-9]+)*$/|max:128')]
    public string $key = '';

    #[Validate('boolean')]
    public bool $isEnabled = false;

    #[Validate('required|integer|min:0|max:100')]
    public int $rolloutPercentage = 100;

    public string $flashMessage = '';
    public string $flashType = 'error';

    public function save(): void
    {
        $this->validate();

        $normalizedKey = strtolower(trim($this->key));

        try {
            app(CommandBus::class)->dispatch(new CreateFeatureFlagCommand(
                key:               $normalizedKey,
                isEnabled:         $this->isEnabled,
                rolloutPercentage: $this->rolloutPercentage,
                actorIdentifier:   $this->actorIdentifier(),
            ));

            $this->redirect(route('ab-testing.feature-flag.show', $normalizedKey), navigate: true);
        } catch (Throwable $e) {
            $this->flashMessage = 'Failed to create feature flag: ' . $e->getMessage();
            $this->flashType    = 'error';
        }
    }

    public function render(): View
    {
        return view('ab-testing::livewire.create-feature-flag')
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — New Feature Flag']);
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
