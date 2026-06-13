<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;

/**
 * Base test case for HTTP feature tests. Boots a minimal Laravel application
 * with the ABTestingServiceProvider registered, an in-memory SQLite database,
 * and all package migrations applied before each test.
 *
 * Gate access is open by default — no gate is defined so RequiresApiAccess
 * passes through. To test authorization, define the gate in the test:
 *
 *   Gate::define('manageAbTestingApi', fn () => false);
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        /** @var Application $app */
        $app = require __DIR__ . '/../Application/bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Package routes are loaded during service-provider boot. Refresh the
        // route-name/action lookup tables so route('...') resolves correctly
        // inside tests against the current application instance.
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();

        // Ensure the gate is not defined so RequiresApiAccess always allows
        // access unless a test explicitly defines it.
        Gate::clearResolvedInstances();
    }

    /**
     * Inject the configured vendor Accept header into every JSON request so all
     * API calls pass EnforceAcceptHeaderMiddleware by default. Tests that need
     * to exercise rejection pass their own Accept value in the $headers array,
     * which takes precedence because it is the second argument to array_merge.
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0): TestResponse
    {
        /** @var string $acceptType */
        $acceptType = config('ab-testing.api.v1.accept_type', 'application/vnd.ab-testing.v1+json');

        $headers = array_merge(['Accept' => $acceptType], $headers);

        return parent::json($method, $uri, $data, $headers, $options);
    }
}
