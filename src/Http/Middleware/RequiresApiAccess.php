<?php

declare(strict_types=1);

namespace ABTests\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate-based access control for the management API. Checks the configured
 * manage_gate before allowing access to any endpoint. Returns a JSON:API
 * errors document rather than redirecting so CI/CD pipelines fail clearly.
 *
 * In production: 404 (avoid leaking endpoint existence).
 * In other environments: 403 with a descriptive JSON:API error body.
 */
final readonly class RequiresApiAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('ab-testing.api.v1.manage_gate', 'manageAbTestingApi');

        if (Gate::has($gate) && Gate::denies($gate)) {
            return app()->isProduction()
                ? $this->errorResponse(Response::HTTP_NOT_FOUND, 'Not Found', 'The requested resource was not found.')
                : $this->errorResponse(Response::HTTP_FORBIDDEN, 'Forbidden', 'Access to the A/B testing API is not authorized.');
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
