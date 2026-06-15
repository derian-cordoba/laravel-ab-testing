<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the Content-Type response header on every API response to the vendor
 * media type configured at ab-testing.api.v1.accept_type. Applied at the route
 * group level so no controller needs to set it manually.
 */
final readonly class SetApiContentTypeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $contentType = config('ab-testing.api.v1.accept_type', 'application/vnd.ab-testing.v1+json');

        $response->headers->set('Content-Type', $contentType);

        return $response;
    }
}
