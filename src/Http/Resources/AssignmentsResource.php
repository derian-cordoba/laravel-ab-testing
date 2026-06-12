<?php

declare(strict_types=1);

namespace ABTests\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\HttpFoundation\Response;

/**
 * JSON representation of the server-resolved experiment assignments for one
 * unit. Wraps the flat key → variant map with unit context so callers can
 * verify they received the correct payload.
 *
 * Shape:
 * {
 *   "data": {
 *     "unit_type":   "user",
 *     "unit_key":    "42",
 *     "assignments": {
 *       "checkout-button-color": "green",
 *       "pricing-page-layout":   "control"
 *     }
 *   }
 * }
 *
 * The Content-Type of the response is set to the configured vendor media type
 * (ab-testing.api.v1.accept_type) so clients can rely on it for version
 * detection.
 */
final class AssignmentsResource extends JsonResource
{
    /**
     * @param array<string, string> $resource Key → variant map of all assignments for the unit.
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{unit_type: string, unit_key: string, assignments: array<string, string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'unit_type'   => $request->unit_type,
            'unit_key'    => $request->unit_key,
            'assignments' => $this->resource,
        ];
    }

    public function withResponse(Request $request, Response $response): void
    {
        /** @var string $contentType */
        $contentType = config('ab-testing.api.v1.accept_type', 'application/vnd.ab-testing.v1+json');

        $response->headers->set('Content-Type', $contentType . '; charset=utf-8');
    }
}
