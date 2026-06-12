<?php

declare(strict_types=1);

namespace ABTests\Http\Middleware;

use ABTests\Blade\BladeDirectiveHelpers;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Injects the current unit's experiment assignments into the response as a
 * JSON-encoded <meta> tag so client-side code can bootstrap from the server's
 * authoritative assignment without a round-trip or re-hash.
 *
 * The unit is identified by two request attributes (set upstream, e.g. in an
 * auth middleware): 'ab_unit_type' and 'ab_unit_key'. If they are not present
 * the middleware is a no-op.
 *
 * The injected tag looks like:
 *   <meta name="ab-assignments" content='{"checkout-button-color":"green"}'>
 *
 * Add the middleware to routes where you want front-end hydration:
 *   Route::middleware(['web', 'ab-testing.expose-assignments'])
 */
final class ExposeAssignmentsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $unitType = $request->attributes->get('ab_unit_type');
        $unitKey  = $request->attributes->get('ab_unit_key');

        if (! is_string($unitType) || ! is_string($unitKey) || $unitType === '' || $unitKey === '') {
            return $response;
        }

        // Only inject into HTML responses.
        $contentType = $response->headers->get('Content-Type', '');

        if (! str_contains($contentType, 'text/html')) {
            return $response;
        }

        $metaTag = BladeDirectiveHelpers::assignmentsMetaTag($unitType, $unitKey);

        if ($metaTag === '') {
            return $response;
        }

        $content = $response->getContent();

        if (is_string($content) && str_contains($content, '</head>')) {
            $content = str_replace('</head>', $metaTag . "\n</head>", $content);
            $response->setContent($content);
        }

        return $response;
    }
}
