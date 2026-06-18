<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Controllers\Api;

use ABTests\Application\Commands\ArchiveExperimentCommand;
use ABTests\Application\Commands\CreateExperimentCommand;
use ABTests\Application\Commands\UpdateExperimentCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Presentation\Http\Requests\Api\CreateExperimentRequest;
use ABTests\Presentation\Http\Requests\Api\UpdateExperimentRequest;
use ABTests\Presentation\Http\Resources\Api\ExperimentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD for runtime-defined experiments. Code-defined experiments (decorated with
 * #[AsExperiment]) are read-only via the API; their structure lives in code.
 */
final readonly class ExperimentsController
{
    public function __construct(private CommandBus $commandBus)
    {
        //
    }

    /**
     * GET /api/ab-testing/experiments
     *
     * Returns a paginated list of all experiments, ordered by most recently updated.
     * Accepts optional ?status= filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ExperimentModel::query()->with('variants')->orderByDesc('updated_at')->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return ExperimentResource::collection($query->paginate(25));
    }

    /**
     * GET /api/ab-testing/experiments/{key}
     */
    public function show(string $key): ExperimentResource
    {
        $model = ExperimentModel::query()->with('variants')->where('key', $key)->firstOrFail();

        return new ExperimentResource($model);
    }

    /**
     * POST /api/ab-testing/experiments
     *
     * Creates a new experiment in draft status. Add variants next with the
     * VariantsController before starting.
     */
    public function store(CreateExperimentRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new CreateExperimentCommand(
            key: $request->string('key')->toString(),
            name: $request->string('name')->toString() ?: null,
            layer: $request->string('layer')->toString() ?: null,
            trafficPercentage: $request->integer('traffic_percentage', 0),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        $model = ExperimentModel::query()->with('variants')->where('key', $request->string('key')->toString())->firstOrFail();

        return (new ExperimentResource($model))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PUT /api/ab-testing/experiments/{key}
     *
     * Updates editable metadata (name, layer, target_sample_size). Layer is
     * locked once the experiment is no longer in draft/scheduled.
     */
    public function update(UpdateExperimentRequest $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new UpdateExperimentCommand(
            experimentKey: $key,
            name: $request->string('name')->toString() ?: null,
            layer: $request->input('layer'),
            targetSampleSize: $request->integer('target_sample_size') ?: null,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        $model = ExperimentModel::query()->with('variants')->where('key', $key)->firstOrFail();

        return new ExperimentResource($model);
    }

    /**
     * DELETE /api/ab-testing/experiments/{key}
     *
     * Archives the experiment (equivalent to the dashboard Archive action). Only
     * completed experiments can be archived. Returns 204 on success.
     */
    public function destroy(Request $request, string $key): JsonResponse
    {
        $this->commandBus->dispatch(new ArchiveExperimentCommand(
            experimentKey: $key,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return response()->json(null, 204);
    }

    private function actorIdentifier(Request $request): string
    {
        if ($request->user() !== null) {
            return (string) $request->user()->getAuthIdentifier();
        }

        return 'api';
    }
}
