<?php

declare(strict_types=1);

namespace ABTests\Providers;

use ABTests\Application\Listeners\AutoPauseOnGuardrailBreachListener;
use ABTests\Application\Listeners\DispatchNotificationListener;
use ABTests\Domain\Events\ExperimentEnvironmentsUpdatedEvent;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Domain\Events\ExperimentStartedEvent;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use ABTests\Domain\Events\FeatureFlagEnvironmentsUpdatedEvent;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Exceptions\ABTestingException;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Infrastructure\Notifications\Channels\MailChannel;
use ABTests\Infrastructure\Notifications\Channels\SlackChannel;
use ABTests\Infrastructure\Notifications\Channels\WebhookChannel;
use ABTests\Infrastructure\Notifications\NotificationDispatcher;
use ABTests\Presentation\Console\CacheDefinitionsCommand;
use ABTests\Presentation\Console\DetectStaleFlagsCommand;
use ABTests\Presentation\Console\PowerAnalysisCommand;
use ABTests\Presentation\Console\PruneEventDataCommand;
use ABTests\Presentation\Http\Middleware\ApiExceptionHandlerMiddleware;
use ABTests\Presentation\Http\Middleware\EnforceAcceptHeaderMiddleware;
use ABTests\Presentation\Http\Middleware\ExposeAssignmentsMiddleware;
use ABTests\Presentation\Http\Middleware\RequiresApiAccess;
use ABTests\Presentation\Http\Middleware\ResolveExperimentMiddleware;
use ABTests\Presentation\Http\Middleware\SetApiContentTypeMiddleware;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as FoundationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registers all presentation-layer services: views, routes, Blade directives,
 * Livewire namespace, middleware aliases, console commands, publishables,
 * outbound notifications, and JSON:API exception rendering.
 */
final class PresentationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationDispatcher::class, fn (): NotificationDispatcher => new NotificationDispatcher(
            webhook: new WebhookChannel(),
            slack: new SlackChannel(),
            mail: new MailChannel(),
        ));
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'ab-testing');
        $this->loadRoutesFrom(__DIR__.'/../Dashboard/routes.php');
        $this->loadRoutesFrom(__DIR__.'/../Api/routes.php');

        // Register anonymous Blade components under the ab-testing:: prefix.
        Blade::anonymousComponentPath(__DIR__.'/../../resources/views/components', 'ab-testing');

        // Register the ab-testing Livewire namespace.
        if (class_exists(Livewire::class)) {
            Livewire::addNamespace('ab-testing', classNamespace: 'ABTests\\Presentation\\Livewire');
        }

        $this->registerBladeDirectives();
        $this->registerMiddlewareAliases();
        $this->registerEventListeners();
        $this->bootApiExceptionRendering();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheDefinitionsCommand::class,
                DetectStaleFlagsCommand::class,
                PruneEventDataCommand::class,
                PowerAnalysisCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/ab-testing.php' => $this->app->configPath('ab-testing.php'),
            ], 'ab-testing-config');

            $this->publishes([
                __DIR__.'/../../database/migrations' => $this->app->databasePath('migrations'),
            ], 'ab-testing-migrations');

            $this->publishes([
                __DIR__.'/../../resources/views' => $this->app->resourcePath('views/vendor/ab-testing'),
            ], 'ab-testing-dashboard-views');
        }

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }

    private function registerBladeDirectives(): void
    {
        // @abVariant('experiment-key', 'variant-key') ... @endAbVariant
        Blade::directive('abVariant', static function (string $expression): string {
            return "<?php if (\\ABTests\\Presentation\\Blade\\BladeDirectiveHelpers::isVariant($expression)): ?>";
        });
        Blade::directive('endAbVariant', static fn (): string => '<?php endif; ?>');

        // @abNotVariant('experiment-key', 'variant-key') ... @endAbNotVariant
        Blade::directive('abNotVariant', static function (string $expression): string {
            return "<?php if (\\ABTests\\Presentation\\Blade\\BladeDirectiveHelpers::isNotVariant($expression)): ?>";
        });
        Blade::directive('endAbNotVariant', static fn (): string => '<?php endif; ?>');

        // @featureEnabled('flag-key') ... @endFeatureEnabled
        Blade::directive('featureEnabled', static function (string $expression): string {
            return "<?php if (\\ABTests\\Presentation\\Blade\\BladeDirectiveHelpers::featureEnabled($expression)): ?>";
        });
        Blade::directive('endFeatureEnabled', static fn (): string => '<?php endif; ?>');

        // @featureDisabled('flag-key') ... @endFeatureDisabled
        Blade::directive('featureDisabled', static function (string $expression): string {
            return "<?php if (\\ABTests\\Presentation\\Blade\\BladeDirectiveHelpers::featureDisabled($expression)): ?>";
        });
        Blade::directive('endFeatureDisabled', static fn (): string => '<?php endif; ?>');

        // @abAssignmentsJson('user', $userId)
        Blade::directive('abAssignmentsJson', static function (string $expression): string {
            return "<?php echo \\ABTests\\Presentation\\Blade\\BladeDirectiveHelpers::assignmentsMetaTag($expression); ?>";
        });
    }

    private function registerMiddlewareAliases(): void
    {
        if (! $this->app->bound(Router::class)) {
            return;
        }

        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('ab-testing.resolve', ResolveExperimentMiddleware::class);
        $router->aliasMiddleware('ab-testing.expose-assignments', ExposeAssignmentsMiddleware::class);
        $router->aliasMiddleware('ab-testing.api-access', RequiresApiAccess::class);
        $router->aliasMiddleware('ab-testing.api-content-type', SetApiContentTypeMiddleware::class);
        $router->aliasMiddleware('ab-testing.enforce-accept', EnforceAcceptHeaderMiddleware::class);
        $router->aliasMiddleware('ab-testing.api-exception-handler', ApiExceptionHandlerMiddleware::class);
    }

    private function registerEventListeners(): void
    {
        Event::listen(GuardrailBreachedEvent::class, AutoPauseOnGuardrailBreachListener::class);

        foreach ([
            ExperimentStartedEvent::class,
            ExperimentPausedEvent::class,
            ExperimentResumedEvent::class,
            ExperimentStoppedEvent::class,
            ExperimentEnvironmentsUpdatedEvent::class,
            FeatureFlagEnabledEvent::class,
            FeatureFlagDisabledEvent::class,
            FeatureFlagEnvironmentsUpdatedEvent::class,
            KillSwitchActivatedEvent::class,
            GuardrailBreachedEvent::class,
        ] as $eventClass) {
            Event::listen($eventClass, DispatchNotificationListener::class);
        }
    }

    /**
     * Register JSON:API error rendering callbacks on the exception handler so
     * that ModelNotFoundException, ValidationException, and domain exceptions
     * thrown from API controllers are converted to consistent JSON:API error
     * documents. Callbacks are scoped to the configured API prefix and only
     * fire when the host app uses the Foundation exception handler.
     */
    private function bootApiExceptionRendering(): void
    {
        $this->callAfterResolving(
            ExceptionHandler::class,
            function (object $handler): void {
                if (! $handler instanceof FoundationHandler) {
                    return;
                }

                /** @var string $prefix */
                $prefix = config(
                    'ab-testing.api.v1.endpoints.experiments.prefix',
                    'api/v1/ab-testing',
                );

                $isApiRequest = static fn (Request $request): bool => $request->is($prefix) || $request->is($prefix.'/*');

                $errorResponse = static function (int $status, string $title, string $detail): JsonResponse {
                    return response()->json([
                        'errors' => [
                            [
                                'status' => (string) $status,
                                'title' => $title,
                                'detail' => $detail,
                            ],
                        ],
                    ], $status);
                };

                $handler->renderable(
                    static function (ModelNotFoundException $e, Request $request) use ($isApiRequest, $errorResponse): ?\Illuminate\Http\JsonResponse {
                        if (! $isApiRequest($request)) {
                            return null;
                        }

                        return $errorResponse(
                            Response::HTTP_NOT_FOUND,
                            'Not Found',
                            'The requested resource was not found.',
                        );
                    },
                );

                $handler->renderable(
                    static function (ValidationException $e, Request $request) use ($isApiRequest): ?\Illuminate\Http\JsonResponse {
                        if (! $isApiRequest($request)) {
                            return null;
                        }

                        $errors = [];

                        foreach ($e->errors() as $field => $messages) {
                            foreach ($messages as $message) {
                                $errors[] = [
                                    'status' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                                    'title' => 'Validation Error',
                                    'detail' => $message,
                                    'source' => ['pointer' => '/data/attributes/'.$field],
                                ];
                            }
                        }

                        return response()->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
                    },
                );

                $handler->renderable(
                    static function (ABTestingException $e, Request $request) use ($isApiRequest, $errorResponse): ?\Illuminate\Http\JsonResponse {
                        if (! $isApiRequest($request)) {
                            return null;
                        }

                        $isNotFound = $e instanceof ExperimentNotFound || $e instanceof FeatureFlagNotFound;

                        $status = $isNotFound
                            ? Response::HTTP_NOT_FOUND
                            : Response::HTTP_UNPROCESSABLE_ENTITY;

                        $title = $isNotFound ? 'Not Found' : 'Unprocessable Entity';

                        return $errorResponse($status, $title, $e->getMessage());
                    },
                );
            },
        );
    }
}
