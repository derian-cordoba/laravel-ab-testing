<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks the configured viewer gate before allowing access to any dashboard
 * route. Returns a 403 if the gate denies access rather than redirecting, so
 * API consumers and partial page loads fail clearly.
 */
final readonly class RequiresDashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $gate = config('ab-testing.dashboard.viewer_gate', 'viewAbTestingDashboard');

        if (Gate::has($gate) && Gate::denies($gate)) {
            if (app()->isProduction()) {
                // When Dashboard is running on production environment, suppress detailed errors to avoid information
                // leakage. In non-production environments, return 403 to aid debugging.
                abort(404, 'Not found.');
            } else {
                abort(403, 'Access to the A/B testing dashboard is not authorized.');
            }
        }

        return $next($request);
    }
}
