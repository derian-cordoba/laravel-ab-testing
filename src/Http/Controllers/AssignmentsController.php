<?php

declare(strict_types=1);

namespace ABTests\Http\Controllers;

use ABTests\Http\Requests\AssignmentsRequest;
use ABTests\Http\Resources\AssignmentsResource;
use ABTests\Infrastructure\Database\Models\AssignmentModel;

/**
 * Exposes the server-resolved experiment assignments for the current request
 * unit as a JSON endpoint, enabling front-end code to read the same assignment
 * without re-hashing. This prevents SSR/CSR variant flicker.
 *
 * The unit is identified via the `unit_type` and `unit_key` query parameters.
 *
 * GET /ab-testing/assignments?unit_type=user&unit_key=42
 *
 * Response:
 * {
 *   "data": {
 *     "unit_type":   "user",
 *     "unit_key":    "42",
 *     "assignments": {"checkout-button-color": "green", "pricing-page-layout": "control"}
 *   }
 * }
 */
final class AssignmentsController
{
    public function __invoke(AssignmentsRequest $request): AssignmentsResource
    {
        /** @var array<string, string> $assignments */
        $assignments = AssignmentModel::query()
            ->where('unit_type', $request->validated('unit_type'))
            ->where('unit_key', $request->validated('unit_key'))
            ->pluck('variant_key', 'experiment_key')
            ->all();

        return new AssignmentsResource(resource: $assignments);
    }
}
