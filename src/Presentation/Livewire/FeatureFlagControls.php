<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Application\Commands\SetFlagConditionsCommand;
use ABTests\Application\Commands\SetFlagEnvironmentsCommand;
use ABTests\Application\Commands\SetFlagRolloutPercentageCommand;
use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Enums\ConditionsLogic;
use ABTests\Enums\Operator;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Throwable;

/**
 * Embedded Livewire component that renders the operational controls for a single
 * feature flag. Dispatches commands through the CommandBus and re-emits
 * 'flag-updated' so the parent FeatureFlagDetail re-renders with fresh state.
 */
final class FeatureFlagControls extends Component
{
    public string $flagKey = '';

    public bool $showKillSwitch = true;

    public bool $showRolloutPercentage = true;

    #[Validate('required|integer|min:0|max:100')]
    public int $rolloutPercentage = 100;

    /** @var list<array{attribute: string, operator: string, expected: mixed}> */
    public array $conditions = [];

    public string $conditionsLogic = 'all';

    /** @var list<string> */
    public array $allowedEnvironments = [];

    public string $newAttribute = '';
    public string $newOperator = 'equals';
    public string $newValue = '';

    public string $flashMessage = '';
    public string $flashType = 'success';

    public function mount(string $flagKey): void
    {
        $this->flagKey = $flagKey;

        $model = FeatureFlagStateModel::query()->firstWhere('key', $flagKey);

        if ($model !== null) {
            $this->rolloutPercentage   = $model->rollout_percentage;
            $this->conditions          = $model->conditions ?? [];
            $this->conditionsLogic     = ($model->conditions_logic ?? ConditionsLogic::all)->value;
            $this->allowedEnvironments = $model->allowed_environments ?? [];
        }
    }

    public function enable(): void
    {
        $this->dispatchCommand(new EnableFeatureFlagCommand(
            flagKey: $this->flagKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function disable(): void
    {
        $this->dispatchCommand(new DisableFeatureFlagCommand(
            flagKey: $this->flagKey,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function setEnvironments(): void
    {
        $this->dispatchCommand(new SetFlagEnvironmentsCommand(
            flagKey: $this->flagKey,
            allowedEnvironments: $this->allowedEnvironments === [] ? null : array_values($this->allowedEnvironments),
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function setRollout(): void
    {
        $this->validateOnly('rolloutPercentage');

        $this->dispatchCommand(new SetFlagRolloutPercentageCommand(
            flagKey: $this->flagKey,
            percentage: $this->rolloutPercentage,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    public function toggleKillSwitch(): void
    {
        $model = FeatureFlagStateModel::query()->firstWhere('key', $this->flagKey);
        $isKilled = $model === null || $model->killed_at === null;

        $this->dispatchCommand(new ToggleFlagKillSwitchCommand(
            flagKey: $this->flagKey,
            isKilled: $isKilled,
            actorIdentifier: $this->actorIdentifier(),
        ));
    }

    /**
     * Append a new condition to the in-memory list. The list is not persisted
     * until saveConditions() is explicitly called.
     */
    public function addCondition(): void
    {
        $attribute = trim($this->newAttribute);
        $value     = trim($this->newValue);

        if ($attribute === '' || $value === '') {
            return;
        }

        $this->conditions[] = [
            'attribute' => $attribute,
            'operator'  => $this->newOperator,
            'expected'  => $this->parseValue($value, $this->newOperator),
        ];

        $this->newAttribute = '';
        $this->newValue     = '';
    }

    /**
     * Remove a condition from the in-memory list by index. Not persisted until
     * saveConditions() is called.
     */
    public function removeCondition(int $index): void
    {
        array_splice($this->conditions, $index, 1);
        $this->conditions = array_values($this->conditions);
    }

    /**
     * Persist the current in-memory conditions list to the database.
     */
    public function saveConditions(): void
    {
        $logic = ConditionsLogic::tryFrom($this->conditionsLogic) ?? ConditionsLogic::all;

        $this->dispatchCommand(new SetFlagConditionsCommand(
            flagKey: $this->flagKey,
            conditions: $this->conditions,
            actorIdentifier: $this->actorIdentifier(),
            conditionsLogic: $logic,
        ));
    }

    public function render(): View
    {
        $model = FeatureFlagStateModel::query()->firstWhere('key', $this->flagKey);

        return view('ab-testing::livewire.feature-flag-controls', compact('model'));
    }

    private function dispatchCommand(object $command): void
    {
        try {
            app(CommandBus::class)->dispatch($command);

            $this->flashMessage = 'Action completed successfully.';
            $this->flashType    = 'success';
            $this->dispatch('flag-updated');
        } catch (Throwable $e) {
            $this->flashMessage = 'An unexpected error occurred: ' . $e->getMessage();
            $this->flashType    = 'error';
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

    /**
     * For 'in' / 'not_in' operators, split a comma-separated string into an
     * array. All other operators keep the raw string value.
     */
    private function parseValue(string $value, string $operator): string|array
    {
        if (in_array($operator, [Operator::in->value, Operator::notIn->value], true)) {
            return array_values(array_filter(
                array_map('trim', explode(',', $value)),
                static fn (string $v): bool => $v !== '',
            ));
        }

        return $value;
    }
}
