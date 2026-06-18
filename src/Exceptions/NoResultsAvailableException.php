<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when an experiment exists but has no rollup data yet, meaning the
 * analysis engine cannot produce any statistics.
 *
 * Renders as a JSON:API errors document so the response shape is consistent
 * with every other API error response.
 */
final class NoResultsAvailableException extends RuntimeException implements ABTestingException
{
    public function __construct(private readonly string $experimentKey)
    {
        parent::__construct(
            "No results are available yet for experiment [$experimentKey].",
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'status' => '404',
                    'title' => 'No Results Available',
                    'detail' => 'No results available yet for this experiment.',
                    'meta' => ['experiment_key' => $this->experimentKey],
                ],
            ],
        ], 404);
    }
}
