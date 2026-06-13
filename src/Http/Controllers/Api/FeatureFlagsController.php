<?php

declare(strict_types=1);

namespace ABTests\Http\Controllers\Api;

use ABTests\Application\Commands\CreateFeatureFlagCommand;
use ABTests\Contracts\CommandBus;
use ABTests\Http\Requests\Api\CreateFeatureFlagRequest;
use ABTests\Http\Resources\Api\FeatureFlagResource;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * CRUD for feature flag state records. Code-defined flags (#[AsFeatureFlag])
 * are read-only via the API; their structure lives in code. Runtime-created
 * flags live entirely in the database and are fully manageable here.
 */
final readonly class FeatureFlagsController
{
    public function __construct(private CommandBus $commandBus)
    {
        //
    }

    /**
     * GET /api/v1/ab-testing/feature-flags
     *
     * Returns a paginated list of all feature flag state records, ordered by
     * most recently updated. Accepts optional ?is_enabled= filter.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FeatureFlagStateModel::query()->orderByDesc('updated_at')->orderByDesc('id');

        if ($request->filled('is_enabled')) {
            $query->where('is_enabled', filter_var($request->input('is_enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        return FeatureFlagResource::collection($query->paginate(25));
    }

    /**
     * GET /api/v1/ab-testing/feature-flags/{key}
     */
    public function show(string $key): FeatureFlagResource
    {
        $model = FeatureFlagStateModel::query()->where('key', $key)->firstOrFail();

        return new FeatureFlagResource($model);
    }

    /**
     * POST /api/v1/ab-testing/feature-flags
     *
     * Creates a new feature flag state record. The flag is disabled by default;
     * use the enable endpoint to activate it.
     */
    public function store(CreateFeatureFlagRequest $request): JsonResponse
    {
        $this->commandBus->dispatch(new CreateFeatureFlagCommand(
            key: $request->string('key')->toString(),
            isEnabled: (bool) $request->input('is_enabled', false),
            rolloutPercentage: $request->integer('rollout_percentage', 100),
            actorIdentifier: $this->actorIdentifier($request),
            actorType: 'api',
        ));

        $model = FeatureFlagStateModel::query()->where('key', $request->string('key')->toString())->firstOrFail();

        return (new FeatureFlagResource($model))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * DELETE /api/v1/ab-testing/feature-flags/{key}
     *
     * Permanently removes the feature flag state record. Returns 204 on success.
     */
    public function destroy(string $key): JsonResponse
    {
        FeatureFlagStateModel::query()->where('key', $key)->firstOrFail()->delete();

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
