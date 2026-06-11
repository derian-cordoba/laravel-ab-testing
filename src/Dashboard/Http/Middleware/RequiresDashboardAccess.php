<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Http\Middleware;

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
            abort(403, 'Access to the A/B testing dashboard is not authorized.');
        }

        return $next($request);
    }
}
