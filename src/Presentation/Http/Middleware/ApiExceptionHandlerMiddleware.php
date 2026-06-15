<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Middleware;

use ABTests\Exceptions\ABTestingException;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\FeatureFlagNotFound;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Converts every exception that escapes the API route group into a JSON:API
 * errors document, ensuring callers always receive a consistent response shape
 * regardless of which layer the failure originates from.
 *
 * Handled exceptions:
 *   ModelNotFoundException  → 404  (Eloquent firstOrFail / findOrFail)
 *   ValidationException     → 422  (FormRequest validation failures)
 *   ABTestingException      → 404 for not-found variants, 422 for all others
 *
 * Unrecognised exceptions are re-thrown so the host application's own handler
 * can deal with them (log to Sentry, render a 500 page, etc.).
 */
final readonly class ApiExceptionHandlerMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (ModelNotFoundException) {
            return $this->errorResponse(
                status: Response::HTTP_NOT_FOUND,
                title: 'Not Found',
                detail: 'The requested resource was not found.',
            );
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (ABTestingException $e) {
            return $this->domainErrorResponse($e);
        }
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

    private function validationErrorResponse(ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                    'title'  => 'Validation Error',
                    'detail' => $message,
                    'source' => ['pointer' => "/data/attributes/$field"],
                ];
            }
        }

        return response()->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function domainErrorResponse(ABTestingException $e): JsonResponse
    {
        $isNotFound = $e instanceof ExperimentNotFound || $e instanceof FeatureFlagNotFound;

        $status = $isNotFound
            ? Response::HTTP_NOT_FOUND
            : Response::HTTP_UNPROCESSABLE_ENTITY;

        $title = $isNotFound ? 'Not Found' : 'Unprocessable Entity';

        return $this->errorResponse($status, $title, $e->getMessage());
    }
}
