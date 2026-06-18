<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Controllers\Api;

use ABTests\Application\Commands\AddVariantCommand;
use ABTests\Application\Commands\RemoveVariantCommand;
use ABTests\Application\Commands\UpdateVariantCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Presentation\Http\Requests\Api\StoreVariantRequest;
use ABTests\Presentation\Http\Requests\Api\UpdateVariantRequest;
use ABTests\Presentation\Http\Resources\Api\ExperimentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages the variant arms of a runtime-defined experiment. Variants must be
 * fully configured (weights summing to 100, exactly one control) before the
 * experiment can be started.
 */
final readonly class VariantsController
{
    public function __construct(private CommandBus $commandBus)
    {
        //
    }

    /**
     * POST /api/ab-testing/experiments/{key}/variants
     *
     * Adds a variant arm. On live experiments existing assignments are never
     * disturbed; only future bucketing is affected.
     */
    public function store(StoreVariantRequest $request, string $key): ExperimentResource
    {
        $this->commandBus->dispatch(new AddVariantCommand(
            experimentKey: $key,
            variantKey: $request->string('key')->toString(),
            weight: $request->integer('weight'),
            isControl: (bool) $request->input('is_control', false),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * PUT /api/ab-testing/experiments/{key}/variants/{id}
     *
     * Updates a variant's key, weight, or control designation. Weight changes
     * only affect future bucketing; sticky assignments are preserved.
     */
    public function update(UpdateVariantRequest $request, string $key, int $id): ExperimentResource
    {
        // Ensure the variant belongs to this experiment.
        VariantModel::query()
            ->whereHas('experiment', static fn ($q) => $q->where('key', $key))
            ->where('id', $id)
            ->firstOrFail();

        $this->commandBus->dispatch(new UpdateVariantCommand(
            experimentKey: $key,
            variantId: $id,
            variantKey: $request->string('key')->toString(),
            weight: $request->integer('weight'),
            isControl: (bool) $request->input('is_control', false),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return $this->fetchExperiment($key);
    }

    /**
     * DELETE /api/ab-testing/experiments/{key}/variants/{id}
     *
     * Removes a variant. The control variant cannot be removed. Units already
     * assigned to the removed variant retain their assignment record but will
     * not receive new bucketing to that variant.
     */
    public function destroy(Request $request, string $key, int $id): JsonResponse
    {
        // Ensure the variant belongs to this experiment.
        VariantModel::query()
            ->whereHas('experiment', static fn ($q) => $q->where('key', $key))
            ->where('id', $id)
            ->firstOrFail();

        $this->commandBus->dispatch(new RemoveVariantCommand(
            experimentKey: $key,
            variantId: $id,
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        return response()->json(null, 204);
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
