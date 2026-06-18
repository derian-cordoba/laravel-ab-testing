<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Controllers\Api;

use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Presentation\Http\Requests\Api\AssignmentsRequest;
use ABTests\Presentation\Http\Resources\Api\AssignmentsResource;
use ABTests\Presentation\Http\Resources\Data\AssignmentsData;

/**
 * Read-only endpoint exposing the current sticky assignments for one unit.
 * This is intended for SSR hydration and JS/TS SDK bootstrap so front-end code
 * can consume the same server-authoritative assignment map without re-bucketing.
 */
final readonly class AssignmentsController
{
    /**
     * GET /api/ab-testing/assignments?unit_type=user&unit_key=42
     */
    public function __invoke(AssignmentsRequest $request): AssignmentsResource
    {
        /** @var array<string, string> $assignments */
        $assignments = AssignmentModel::query()
            ->where('unit_type', $request->validated('unit_type'))
            ->where('unit_key', $request->validated('unit_key'))
            ->pluck('variant_key', 'experiment_key')
            ->all();

        return new AssignmentsResource(new AssignmentsData(
            unitType: $request->validated('unit_type'),
            unitKey: $request->validated('unit_key'),
            assignments: $assignments,
        ));
    }
}
