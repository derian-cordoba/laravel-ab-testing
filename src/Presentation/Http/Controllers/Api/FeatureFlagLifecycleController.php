<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Controllers\Api;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Application\Commands\SetFlagConditionsCommand;
use ABTests\Application\Commands\SetFlagRolloutPercentageCommand;
use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Enums\ConditionsLogic;
use ABTests\Presentation\Http\Requests\Api\SetFlagConditionsRequest;
use ABTests\Presentation\Http\Requests\Api\SetRolloutPercentageRequest;
use ABTests\Presentation\Http\Resources\Api\FeatureFlagResource;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operational controls for a feature flag. Each action maps to a command and
 * is designed to mirror the dashboard controls so CI/CD pipelines can drive
 * flag state programmatically.
 *
 * Typical gradual-rollout flow:
 *   POST /enable → POST /rollout { rollout_percentage: 10 } → verify → POST /rollout { rollout_percentage: 100 }
 */
final readonly class FeatureFlagLifecycleController
{
    public function __construct(private CommandBus $commandBus)
    {
        //
    }

    /**
     * POST /api/v1/ab-testing/feature-flags/{key}/enable
     *
     * Enables the flag. Units within the rollout percentage will receive the
     * flag's active value on the next resolution.
     */
    public function enable(Request $request, string $key): FeatureFlagResource
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new EnableFeatureFlagCommand(
            flagKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchFlag($key);
    }

    /**
     * POST /api/v1/ab-testing/feature-flags/{key}/disable
     *
     * Disables the flag. All units will receive the default value on the next
     * resolution, regardless of rollout percentage.
     */
    public function disable(Request $request, string $key): FeatureFlagResource
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new DisableFeatureFlagCommand(
            flagKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchFlag($key);
    }

    /**
     * POST /api/v1/ab-testing/feature-flags/{key}/rollout
     *
     * Sets the percentage of eligible units that receive the enabled flag.
     * Useful for gradual rollouts without toggling the flag on and off.
     *
     * Body: { "rollout_percentage": 25 }
     */
    public function rollout(SetRolloutPercentageRequest $request, string $key): FeatureFlagResource
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new SetFlagRolloutPercentageCommand(
            flagKey: $key,
            percentage: $request->integer('rollout_percentage'),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchFlag($key);
    }

    /**
     * POST /api/v1/ab-testing/feature-flags/{key}/kill-switch
     *
     * Activates the kill switch. When active, all units immediately receive the
     * flag's default value, bypassing enabled state and rollout percentage.
     */
    public function killSwitch(Request $request, string $key): FeatureFlagResource
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new ToggleFlagKillSwitchCommand(
            flagKey: $key,
            isKilled: (bool) $request->input('is_killed', true),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchFlag($key);
    }

    /**
     * POST /api/v1/ab-testing/feature-flags/{key}/kill-switch/deactivate
     *
     * Convenience alias that always deactivates the kill switch. Returns the
     * flag to its normal enabled/disabled + rollout behaviour.
     */
    public function deactivateKillSwitch(Request $request, string $key): FeatureFlagResource
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new ToggleFlagKillSwitchCommand(
            flagKey: $key,
            isKilled: false,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchFlag($key);
    }

    /**
     * POST /api/v1/ab-testing/feature-flags/{key}/conditions
     *
     * Replaces the targeting conditions for the flag. An empty array removes
     * all conditions so every unit within the rollout percentage is eligible.
     *
     * Body:
     * {
     *   "conditions": [
     *     { "attribute": "plan", "operator": "equals", "expected": "pro" }
     *   ],
     *   "conditions_logic": "all"
     * }
     */
    public function conditions(SetFlagConditionsRequest $request, string $key): FeatureFlagResource
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new SetFlagConditionsCommand(
            flagKey: $key,
            conditions: $request->array('conditions'),
            actorIdentifier: $this->actorIdentifier($request),
            conditionsLogic: ConditionsLogic::from($request->string('conditions_logic', 'all')->toString()),
            actorType: 'api',
        ));

        return $this->fetchFlag($key);
    }

    /**
     * DELETE /api/v1/ab-testing/feature-flags/{key}/conditions
     *
     * Removes all targeting conditions from the flag. Every unit within the
     * rollout percentage becomes eligible.
     */
    public function clearConditions(Request $request, string $key): JsonResponse
    {
        $this->requireFlag($key);

        $this->commandBus->dispatch(new SetFlagConditionsCommand(
            flagKey: $key,
            conditions: [],
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return response()->json(null, 204);
    }

    private function fetchFlag(string $key): FeatureFlagResource
    {
        $model = FeatureFlagStateModel::query()->where('key', $key)->firstOrFail();

        return new FeatureFlagResource($model);
    }

    private function requireFlag(string $key): void
    {
        FeatureFlagStateModel::query()->where('key', $key)->firstOrFail();
    }

    private function actorIdentifier(Request $request): string
    {
        if ($request->user() !== null) {
            return (string) $request->user()->getAuthIdentifier();
        }

        return 'api';
    }
}
