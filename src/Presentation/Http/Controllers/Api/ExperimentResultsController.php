<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Controllers\Api;

use ABTests\Application\DTOs\VerdictData;
use ABTests\Application\ResultsService;
use ABTests\Exceptions\NoResultsAvailableException;
use ABTests\Presentation\Http\Resources\Api\ExperimentResultsResource;
use ABTests\Presentation\Http\Resources\Api\VerdictResource;
use ABTests\Infrastructure\Database\Models\ExperimentModel;

/**
 * Read-only results and verdict endpoints. These are the key CI/CD integration
 * points: poll /results to monitor progress, call /verdict to get the final
 * ship/do-not-ship/inconclusive recommendation.
 *
 * Both endpoints read only the rollups table (no raw event scans) and are safe
 * to call frequently. All responses are serialized through dedicated resources
 * so the JSON:API shape is enforced uniformly.
 */
final readonly class ExperimentResultsController
{
    public function __construct(private ResultsService $resultsService)
    {
        //
    }

    /**
     * GET /api/ab-testing/experiments/{key}/results
     *
     * Returns full per-variant statistics including both frequentist and Bayesian
     * engine outputs, SRM diagnostic, and active guardrail breaches. The payload
     * is the same data the dashboard renders.
     *
     * @throws NoResultsAvailableException When the experiment exists but has no rollup data.
     */
    public function show(string $key): ExperimentResultsResource
    {
        ExperimentModel::query()->where('key', $key)->firstOrFail();

        $results = $this->resultsService->forExperiment($key);

        if ($results === null || ! $results->hasResults()) {
            throw new NoResultsAvailableException($key);
        }

        return new ExperimentResultsResource($results);
    }

    /**
     * GET /api/ab-testing/experiments/{key}/verdict
     *
     * The CI/CD decision endpoint. Returns the overall experiment recommendation
     * (ship / do_not_ship / inconclusive) and per-treatment-variant details.
     * Designed to be polled after POST /stop, then used to drive a ship-or-rollback
     * decision in a deployment pipeline.
     *
     * All three outcome paths — no data, SRM detected, and a full statistical
     * result — are handled by VerdictData and serialized through VerdictResource
     * with the same JSON:API shape.
     */
    public function verdict(string $key): VerdictResource
    {
        ExperimentModel::query()->where('key', $key)->firstOrFail();

        $results = $this->resultsService->forExperiment($key);

        if ($results === null || ! $results->hasResults()) {
            return new VerdictResource(VerdictData::noResults(
                experimentKey: $key,
                status: $results?->model->status ?? 'unknown',
            ));
        }

        return new VerdictResource(VerdictData::fromResults(
            experimentKey: $key,
            results: $results,
        ));
    }
}
