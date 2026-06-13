<?php

declare(strict_types=1);

namespace ABTests\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects any request whose Accept header does not exactly include the
 * configured vendor media type for this API version. Wildcards such as *\/*
 * are intentionally rejected so that casual browser or misconfigured client
 * traffic never reaches the management endpoints.
 *
 * In production: 404 (avoid leaking endpoint existence).
 * In other environments: 406 with a JSON:API error body.
 */
final readonly class EnforceAcceptHeaderMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var string $required */
        $required = config('ab-testing.api.v1.accept_type', 'application/vnd.ab-testing.v1+json');

        if (! in_array($required, $request->getAcceptableContentTypes(), strict: true)) {
            return app()->isProduction()
                ? $this->errorResponse(Response::HTTP_NOT_FOUND, 'Not Found', 'The requested resource was not found.')
                : $this->errorResponse(Response::HTTP_NOT_ACCEPTABLE, 'Not Acceptable', "This endpoint requires Accept: {$required}.");
        }

        return $next($request);
    }

    private function errorResponse(int $status, string $title, string $detail): JsonResponse
    {
        return response()->json([
            'errors' => [
                [
                    'status' => (string) $status,
                    'title'  => $title,
                    'detail' => $detail,
                ],
            ],
        ], $status);
    }
}
