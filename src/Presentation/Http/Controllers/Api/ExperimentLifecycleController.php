<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Controllers\Api;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Application\Commands\RampTrafficCommand;
use ABTests\Application\Commands\ResumeExperimentCommand;
use ABTests\Application\Commands\StartExperimentCommand;
use ABTests\Application\Commands\StopExperimentCommand;
use ABTests\Application\Commands\ToggleKillSwitchCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Presentation\Http\Requests\Api\RampTrafficRequest;
use ABTests\Presentation\Http\Resources\Api\ExperimentResource;
use Illuminate\Http\Request;

/**
 * Lifecycle transitions for experiments. Each action maps 1-to-1 to a command,
 * following the same state machine the dashboard drives. All actions are
 * audit-logged via the command handlers.
 *
 * Typical CI/CD flow:
 *   POST /start → monitor via GET /results → POST /stop → GET /verdict → ship or rollback
 */
final readonly class ExperimentLifecycleController
{
    public function __construct(private CommandBus $commandBus)
    {
        //
    }

    /**
     * POST /api/ab-testing/experiments/{key}/start
     *
     * Transitions draft/scheduled → running. Requires at least one variant
     * and (when governance.approval_required is on) an approval record.
     */
    public function start(Request $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new StartExperimentCommand(
            experimentKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * POST /api/ab-testing/experiments/{key}/pause
     *
     * Transitions running → paused. Assignment and event recording stop.
     */
    public function pause(Request $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new PauseExperimentCommand(
            experimentKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * POST /api/ab-testing/experiments/{key}/resume
     *
     * Transitions paused → running.
     */
    public function resume(Request $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new ResumeExperimentCommand(
            experimentKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * POST /api/ab-testing/experiments/{key}/stop
     *
     * Transitions running/paused → completed. No further assignments occur.
     */
    public function stop(Request $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new StopExperimentCommand(
            experimentKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * POST /api/ab-testing/experiments/{key}/traffic
     *
     * Ramps the traffic percentage without changing status. Useful for gradual
     * rollouts: start at 5%, verify, ramp to 50%, then to 100%.
     *
     * Body: { "traffic_percentage": 50 }
     */
    public function rampTraffic(RampTrafficRequest $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new RampTrafficCommand(
            experimentKey: $key,
            trafficPercentage: $request->integer('traffic_percentage'),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * POST /api/ab-testing/experiments/{key}/kill-switch
     *
     * Toggles the kill switch. When active, all units receive the control
     * variant without any bucketing, regardless of experiment status.
     *
     * Body: { "is_killed": true }
     */
    public function killSwitch(Request $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new ToggleKillSwitchCommand(
            experimentKey: $key,
            isKilled: (bool) $request->input('is_killed', true),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * POST /api/ab-testing/experiments/{key}/kill-switch/deactivate
     *
     * Convenience alias that always deactivates the kill switch.
     */
    public function deactivateKillSwitch(Request $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new ToggleKillSwitchCommand(
            experimentKey: $key,
            isKilled: false,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    private function fetchExperiment(string $key): ExperimentResource
    {
        $model = ExperimentModel::query()->with('variants')->where('key', $key)->firstOrFail();

        return new ExperimentResource($model);
    }

    private function actorIdentifier(Request $request): string
    {
        if ($request->user() !== null) {
            return (string) $request->user()->getAuthIdentifier();
        }

        return 'api';
    }
}
