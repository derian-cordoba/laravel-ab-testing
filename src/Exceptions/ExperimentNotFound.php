<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Thrown when a resolution or lookup references an experiment key or class
 * that has not been registered in the ExperimentRegistry.
 */
final class ExperimentNotFound extends RuntimeException implements ABTestingException
{
    public function __construct(string $identifier)
    {
        parent::__construct(
            "Experiment [$identifier] is not registered. Did you add it to the registry or run php artisan ab:cache?",
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'status' => '404',
                    'title' => 'Not Found',
                    'detail' => 'Experiment not found.',
                ],
            ],
        ], 404);
    }
}
