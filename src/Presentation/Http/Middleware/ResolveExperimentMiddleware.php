<?php

declare(strict_types=1);

namespace ABTests\Presentation\Http\Middleware;

use ABTests\Attributes\ResolvesExperiment;
use ABTests\Contracts\Bucketable;
use ABTests\Experiments;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads every #[ResolvesExperiment] attribute on the matched controller action,
 * resolves a variant for the current unit, and binds each resolved variant into
 * the IoC container so that Laravel's method injection can hand it to the
 * controller as a typed parameter.
 *
 * Unit resolution order:
 *  1. The first route parameter that implements Bucketable.
 *  2. Auth::user() if it implements Bucketable.
 *
 * If no unit can be found for a given experiment, that experiment is skipped
 * silently — the controller method receives whatever the container's default
 * resolution would produce. (Typically a BindingResolutionException if the
 * variant type is not bound elsewhere, so callers should ensure a unit is
 * always available when using this middleware.)
 *
 * Exposure is recorded automatically inside ExperimentResolver::variant().
 *
 * Usage:
 *   Route::get('/checkout', [CheckoutController::class, 'show'])
 *       ->middleware('ab-testing.resolve');
 *
 * Controller:
 *   #[ResolvesExperiment(CheckoutButtonColor::class)]
 *   public function show(Tenant $tenant, ButtonColor $variant): View { ... }
 */
final class ResolveExperimentMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $this->bindResolvedVariants($request);

        return $next($request);
    }

    private function bindResolvedVariants(Request $request): void
    {
        $attributes = $this->experimentAttributes($request);

        if ($attributes === []) {
            return;
        }

        $unit = $this->resolveUnit($request);

        if ($unit === null) {
            return;
        }

        $resolver = Experiments::for($unit);

        foreach ($attributes as $attribute) {
            $variant = $resolver->variant($attribute->experiment);

            if ($variant === null) {
                continue;
            }

            // Bind the resolved variant into the container keyed by its class
            // so that method injection can satisfy typed parameters like:
            //   public function show(ButtonColor $variant): View
            $variantClass = get_class($variant);

            app()->bind($variantClass, static fn (): mixed => $variant);
        }
    }

    /**
     * @return list<ResolvesExperiment>
     */
    private function experimentAttributes(Request $request): array
    {
        $uses = $request->route()?->getAction('uses');

        if (! is_string($uses) || ! str_contains($uses, '@')) {
            return [];
        }

        [$controllerClass, $method] = explode('@', $uses, 2);

        if (! class_exists($controllerClass)) {
            return [];
        }

        try {
            $reflection = new ReflectionMethod($controllerClass, $method);
        } catch (ReflectionException) {
            return [];
        }

        $reflectionAttributes = $reflection->getAttributes(ResolvesExperiment::class);

        return array_map(
            static fn ($attr): object => $attr->newInstance(),
            $reflectionAttributes,
        );
    }

    private function resolveUnit(Request $request): ?Bucketable
    {
        // 1. First route parameter implementing Bucketable.
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof Bucketable) {
                return $parameter;
            }
        }

        // 2. Authenticated user if it implements Bucketable.
        $user = Auth::user();

        if ($user instanceof Bucketable) {
            return $user;
        }

        return null;
    }
}
