<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Application\Listeners\AutoPauseOnGuardrailBreachListener;
use ABTests\Application\Listeners\DispatchNotificationListener;
use ABTests\Application\ResultsService;
use ABTests\Presentation\Blade\BladeDirectiveHelpers;
use ABTests\Exceptions\ABTestingException;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Presentation\Http\Middleware\ApiExceptionHandlerMiddleware;
use ABTests\Presentation\Http\Middleware\ExposeAssignmentsMiddleware;
use ABTests\Presentation\Http\Middleware\EnforceAcceptHeaderMiddleware;
use ABTests\Presentation\Http\Middleware\RequiresApiAccess;
use ABTests\Presentation\Http\Middleware\SetApiContentTypeMiddleware;
use ABTests\Presentation\Http\Middleware\ResolveExperimentMiddleware;
use ABTests\Application\SynchronousCommandBus;
use ABTests\Presentation\Console\CacheDefinitionsCommand;
use ABTests\Presentation\Console\DetectStaleFlagsCommand;
use ABTests\Presentation\Console\PowerAnalysisCommand;
use ABTests\Presentation\Console\PruneEventDataCommand;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\CommandBus;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentRepository;
use ABTests\Infrastructure\Database\DatabaseFeatureFlagRepository;
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
use ABTests\Infrastructure\Notifications\Channels\MailChannel;
use ABTests\Infrastructure\Notifications\Channels\SlackChannel;
use ABTests\Infrastructure\Notifications\Channels\WebhookChannel;
use ABTests\Infrastructure\Notifications\NotificationDispatcher;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use ABTests\Infrastructure\Database\DatabaseAssignmentRepository;
use ABTests\Infrastructure\Database\DatabaseEventSink;
use ABTests\Infrastructure\Database\DatabaseExperimentStateRepository;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\Jobs\PruneEventDataJob;
use ABTests\Infrastructure\Jobs\RefreshRollupsJob;
use ABTests\Infrastructure\NullEventSink;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ClassDiscovery;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Application\Resolution\Resolver;
use ABTests\Application\Resolution\Steps\BucketStep;
use ABTests\Application\Resolution\Steps\CheckEnvironmentStep;
use ABTests\Application\Resolution\Steps\CheckExperimentActiveStep;
use ABTests\Application\Resolution\Steps\CheckLayerExclusionStep;
use ABTests\Application\Resolution\Steps\CheckSegmentStep;
use ABTests\Application\Resolution\Steps\CheckTrafficAllocationStep;
use ABTests\Application\Resolution\Steps\LoadExistingAssignmentStep;
use ABTests\Application\Resolution\Steps\PersistAssignmentStep;
use ABTests\Domain\Analysis\AnalysisService;
use ABTests\Domain\Analysis\BayesianAnalysisEngine;
use ABTests\Domain\Analysis\FrequentistAnalysisEngine;
use ABTests\Domain\Analysis\SampleRatioMismatchDetector;
use ABTests\Domain\Analysis\VerdictResolver;
use ABTests\Infrastructure\Bucketing\Sha256BucketingStrategy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as FoundationHandler;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Wires the framework into the Laravel container. Binds all contracts to their
 * default implementations and populates the ExperimentRegistry from the
 * explicitly configured class list.
 *
 * Default bindings (all swappable via config or by re-binding in AppServiceProvider):
 *
 *   BucketingStrategy         → Sha256BucketingStrategy
 *   AssignmentRepository      → DatabaseAssignmentRepository      (database driver, default)
 *                             → InMemoryAssignmentRepository      (in_memory driver)
 *   ExperimentStateRepository → DatabaseExperimentStateRepository (database driver, default)
 *                             → AlwaysRunningExperimentStateRepository (in_memory driver)
 *   EventSink                 → DatabaseEventSink  (database driver)
 *                             → NullEventSink      (in_memory driver)
 *   CommandBus                → SynchronousCommandBus
 *   ResultsService            → ResultsService (TTL-cached)
 */
final class ABTestingServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/ab-testing.php',
            key: 'ab-testing',
        );

        $this->app->singleton(BucketingStrategy::class, Sha256BucketingStrategy::class);

        $this->bindStorageDriver();

        $this->app->singleton(ExperimentRegistry::class, function (): ExperimentRegistry {
            $registry = new ExperimentRegistry();
            $reader = new AttributeReader();

            /** @var list<class-string<Experiment>> $classes */
            $classes = $this->app->make(ConfigRepository::class)->get('ab-testing.experiments', []);

            foreach ($classes as $class) {
                try {
                    $definition = $reader->readExperiment($class);
                    $registry->register($definition, $class);
                } catch (Throwable $e) {
                    // Log rather than crash at boot time; the ab:cache command
                    // surfaces individual failures with better context.
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Failed to register experiment [$class]: {$e->getMessage()}"
                        );
                    }
                }
            }

            return $registry;
        });

        $this->app->singleton(FeatureFlagRegistry::class, function (): FeatureFlagRegistry {
            $registry = new FeatureFlagRegistry();
            $reader = new AttributeReader();

            /** @var list<class-string<FeatureFlag>> $classes */
            $classes = $this->app->make(ConfigRepository::class)->get('ab-testing.feature_flags.register', []);

            foreach ($classes as $class) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $registry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Failed to register feature flag [$class]: {$e->getMessage()}"
                        );
                    }
                }
            }

            return $registry;
        });

        $this->app->singleton(Resolver::class, function (): Resolver {
            $assignmentRepository = $this->app->make(AssignmentRepository::class);

            return new Resolver(
                bucketingStrategy: $this->app->make(BucketingStrategy::class),
                stateRepository: $this->app->make(ExperimentStateRepository::class),
                steps: [
                    new CheckExperimentActiveStep(),
                    new CheckEnvironmentStep(),
                    new CheckSegmentStep(),
                    new CheckTrafficAllocationStep(),
                    new LoadExistingAssignmentStep($assignmentRepository),
                    new CheckLayerExclusionStep($assignmentRepository),
                    new BucketStep(),
                    new PersistAssignmentStep($assignmentRepository),
                ],
            );
        });

        $this->app->singleton(FrequentistAnalysisEngine::class, FrequentistAnalysisEngine::class);
        $this->app->singleton(BayesianAnalysisEngine::class, BayesianAnalysisEngine::class);
        $this->app->singleton(SampleRatioMismatchDetector::class, SampleRatioMismatchDetector::class);
        $this->app->singleton(VerdictResolver::class, VerdictResolver::class);

        $this->app->singleton(AnalysisService::class, function (): AnalysisService {
            return new AnalysisService(
                frequentistEngine: $this->app->make(FrequentistAnalysisEngine::class),
                bayesianEngine: $this->app->make(BayesianAnalysisEngine::class),
                srmDetector: $this->app->make(SampleRatioMismatchDetector::class),
                verdictResolver: $this->app->make(VerdictResolver::class),
            );
        });

        $this->app->singleton(ResultsService::class, function (): ResultsService {
            return new ResultsService(
                registry: $this->app->make(ExperimentRegistry::class),
                analysisService: $this->app->make(AnalysisService::class),
            );
        });

        $this->app->singleton(
            CommandBus::class,
            fn (): SynchronousCommandBus => new SynchronousCommandBus($this->app),
        );

        $this->app->singleton(
            Experiments::class,
            fn (): Experiments => new Experiments(
                registry: $this->app->make(ExperimentRegistry::class),
                flagRegistry: $this->app->make(FeatureFlagRegistry::class),
                resolver: $this->app->make(Resolver::class),
                eventSink: $this->app->make(EventSink::class),
                assignmentRepository: $this->app->make(AssignmentRepository::class),
                bucketingStrategy: $this->app->make(BucketingStrategy::class),
                featureFlagRepository: $this->app->make(FeatureFlagRepository::class),
            ),
        );
    }

    /**
     * If auto-discovery is enabled, scan the configured paths and register any
     * Experiment or FeatureFlag subclass found into the appropriate registry.
     * Runs after the singleton factories have already populated the explicit
     * 'experiments' and 'feature_flags' lists, so discovered classes are
     * additive — duplicates are silently skipped by the registries.
     *
     * @throws BindingResolutionException
     */
    private function bootDiscovery(): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->app->make(ConfigRepository::class)->get('ab-testing', []);

        if (! ($config['discovery']['enabled'] ?? false)) {
            return;
        }

        /** @var list<string> $paths */
        $paths = $config['discovery']['paths'] ?? [];

        if ($paths === []) {
            return;
        }

        $discovered = new ClassDiscovery()->discover($paths);

        if ($discovered === []) {
            return;
        }

        $reader           = new AttributeReader();
        $experimentRegistry = $this->app->make(ExperimentRegistry::class);
        $flagRegistry       = $this->app->make(FeatureFlagRegistry::class);

        foreach ($discovered as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (is_a($class, Experiment::class, true)) {
                try {
                    $definition = $reader->readExperiment($class);
                    $experimentRegistry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Discovery: failed to register experiment [$class]: {$e->getMessage()}"
                        );
                    }
                }

                continue;
            }

            if (is_a($class, FeatureFlag::class, true)) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $flagRegistry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Discovery: failed to register feature flag [$class]: {$e->getMessage()}"
                        );
                    }
                }
            }
        }
    }

    /**
     * Register JSON:API error rendering callbacks on the exception handler so
     * that ModelNotFoundException, ValidationException, and domain exceptions
     * thrown from API controllers are converted to consistent JSON:API errors
     * documents. Callbacks are scoped to the configured API prefix and only
     * fire when the host app uses the Foundation exception handler.
     *
     * Illuminate\Routing\Pipeline catches exceptions thrown inside the route
     * middleware stack and renders them via the exception handler before they
     * can bubble up to ApiExceptionHandlerMiddleware's try/catch. These
     * renderable() callbacks intercept them at that render step instead.
     */
    private function bootApiExceptionRendering(): void
    {
        $this->callAfterResolving(
            \Illuminate\Contracts\Debug\ExceptionHandler::class,
            function (object $handler): void {
                if (! $handler instanceof FoundationHandler) {
                    return;
                }

                /** @var string $prefix */
                $prefix = config(
                    'ab-testing.api.v1.endpoints.experiments.prefix',
                    'api/v1/ab-testing',
                );

                $isApiRequest = static fn (Request $request): bool =>
                    $request->is($prefix) || $request->is($prefix . '/*');

                $errorResponse = static function (int $status, string $title, string $detail): \Illuminate\Http\JsonResponse {
                    return response()->json([
                        'errors' => [
                            [
                                'status' => (string) $status,
                                'title'  => $title,
                                'detail' => $detail,
                            ],
                        ],
                    ], $status);
                };

                // ModelNotFoundException → 404
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
                    }
                );

                // ValidationException → 422 with per-field JSON:API errors
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
                                    'title'  => 'Validation Error',
                                    'detail' => $message,
                                    'source' => ['pointer' => '/data/attributes/' . $field],
                                ];
                            }
                        }

                        return response()->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
                    }
                );

                // ABTestingException → 404 (not-found) or 422 (all others)
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
                    }
                );
            }
        );
    }

    /**
     * Bind the storage driver contracts to their implementations. 'database' is
     * the production default; 'in_memory' is suitable for tests and local
     * development without a database.
     *
     * @throws BindingResolutionException
     */
    private function bindStorageDriver(): void
    {
        /** @var string $driver */
        $driver = $this->app->make(ConfigRepository::class)->get('ab-testing.storage.driver', 'database');

        if ($driver === 'in_memory') {
            $this->app->singleton(AssignmentRepository::class, InMemoryAssignmentRepository::class);
            $this->app->singleton(ExperimentStateRepository::class, AlwaysRunningExperimentStateRepository::class);
            $this->app->singleton(EventSink::class, NullEventSink::class);

            return;
        }

        // 'database' driver — Eloquent implementations backed by PostgreSQL/MySQL.
        $this->app->singleton(AssignmentRepository::class, DatabaseAssignmentRepository::class);
        $this->app->singleton(ExperimentStateRepository::class, DatabaseExperimentStateRepository::class);
        $this->app->singleton(EventSink::class, DatabaseEventSink::class);
        $this->app->singleton(ExperimentRepository::class, DatabaseExperimentRepository::class);
        $this->app->singleton(FeatureFlagRepository::class, DatabaseFeatureFlagRepository::class);
        $this->app->singleton(AuditLogRepository::class, DatabaseAuditLogRepository::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        Experiments::setInstance($this->app->make(Experiments::class));

        $this->bootDiscovery();
        $this->bootApiExceptionRendering();

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ab-testing');
        $this->loadRoutesFrom(__DIR__ . '/Dashboard/routes.php');
        $this->loadRoutesFrom(__DIR__ . '/Api/routes.php');

        // Register anonymous Blade components under the ab-testing:: prefix so
        // <x-ab-testing::status-badge> and <x-ab-testing::verdict-badge> resolve
        // to resources/views/components/*.blade.php.
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', 'ab-testing');

        // Register the ab-testing Livewire namespace so the :: resolver can
        // auto-discover all components in ABTests\Presentation\Livewire, including
        // experiments-overview, experiment-detail, experiment-controls, and
        // experiment-results-table, without individual registrations.
        if (class_exists(Livewire::class)) {
            Livewire::addNamespace('ab-testing', classNamespace: 'ABTests\\Presentation\\Livewire');
        }

        // ── Blade directives ─────────────────────────────────────────────────
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
        // Renders a <meta name="ab-assignments" content='{"experiment-key":"variant"}'>
        // tag for the given unit so JS can bootstrap from the server assignment.
        Blade::directive('abAssignmentsJson', static function (string $expression): string {
            return "<?php echo \\ABTests\\Presentation\\Blade\\BladeDirectiveHelpers::assignmentsMetaTag($expression); ?>";
        });
        // ─────────────────────────────────────────────────────────────────────

        // Register middleware aliases.
        if ($this->app->bound(Router::class)) {
            $router = $this->app->make(Router::class);
            // ->middleware('ab-testing.resolve') on routes with #[ResolvesExperiment]
            $router->aliasMiddleware('ab-testing.resolve', ResolveExperimentMiddleware::class);
            // ->middleware('ab-testing.expose-assignments') injects server assignments into HTML
            $router->aliasMiddleware('ab-testing.expose-assignments', ExposeAssignmentsMiddleware::class);
            // ->middleware('ab-testing.api-access') gate-checks the management API
            $router->aliasMiddleware('ab-testing.api-access', RequiresApiAccess::class);
            // ->middleware('ab-testing.api-content-type') sets the vendor Content-Type header
            $router->aliasMiddleware('ab-testing.api-content-type', SetApiContentTypeMiddleware::class);
            // ->middleware('ab-testing.enforce-accept') rejects requests missing the vendor Accept header
            $router->aliasMiddleware('ab-testing.enforce-accept', EnforceAcceptHeaderMiddleware::class);
            // ->middleware('ab-testing.api-exception-handler') converts exceptions to JSON:API errors
            $router->aliasMiddleware('ab-testing.api-exception-handler', ApiExceptionHandlerMiddleware::class);
        }

        // Guardrail breach → auto-pause listener.
        Event::listen(GuardrailBreachedEvent::class, AutoPauseOnGuardrailBreachListener::class);

        // Register the NotificationDispatcher singleton so channels are
        // instantiated once and reused across the queued job.
        $this->app->singleton(NotificationDispatcher::class, fn (): NotificationDispatcher => new NotificationDispatcher(
            webhook: new WebhookChannel(),
            slack: new SlackChannel(),
            mail: new MailChannel(),
        ));

        // Outbound notification listeners — one central listener handles all
        // supported event types and converts them to a NotificationPayload.
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

        // Flush the DatabaseEventSink buffer at the end of every request.
        /** @var string $driver */
        $driver = $this->app->make(ConfigRepository::class)->get('ab-testing.storage.driver', 'database');

        if ($driver === 'database') {
            $this->app->terminating(function (): void {
                /** @var DatabaseEventSink $sink */
                $sink = $this->app->make(EventSink::class);
                $sink->flush();
            });
        }

        // Optional scheduler registration for the rollup job.
        if ($this->app->make(ConfigRepository::class)->get('ab-testing.dashboard.auto_schedule_rollups', true)) {
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->job(RefreshRollupsJob::class)->everyFiveMinutes();
            });
        }

        // Optional scheduler registration for the event-data pruning job.
        if ($this->app->make(ConfigRepository::class)->get('ab-testing.retention.auto_schedule', true)) {
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->job(PruneEventDataJob::class)->weekly()->sundays()->atMidnight();
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                CacheDefinitionsCommand::class,
                DetectStaleFlagsCommand::class,
                PruneEventDataCommand::class,
                PowerAnalysisCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/ab-testing.php' => $this->app->configPath('ab-testing.php'),
            ], 'ab-testing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'ab-testing-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/ab-testing'),
            ], 'ab-testing-dashboard-views');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
