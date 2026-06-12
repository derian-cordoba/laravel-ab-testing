<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Application\CommandHandlers\ForgetUnitCommandHandler;
use ABTests\Application\CommandHandlers\RecordCovariateCommandHandler;
use ABTests\Application\Commands\ForgetUnitCommand;
use ABTests\Application\Commands\RecordCovariateCommand;
use ABTests\Attributes\AsFeatureFlag;
use ABTests\Attributes\AsMetric;
use Illuminate\Support\Carbon;
use RuntimeException;
use Throwable;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Enums\Environment;
use ABTests\Enums\Operator;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Registry\FeatureFlagRegistry;
use ABTests\Values\Context;
use ABTests\Values\Segment;

/**
 * Primary entry point for the A/B testing framework.
 *
 * Usage:
 *
 *   $variant = Experiments::for($tenant)->variant(CheckoutButtonColor::class);
 *
 *   return match ($variant) {
 *       ButtonColor::green   => view('checkout.green'),
 *       ButtonColor::blue    => view('checkout.blue'),
 *       ButtonColor::control => view('checkout.default'),
 *   };
 *
 *   Experiments::track(CheckoutConversion::class, for: $tenant);
 *
 * The static façade delegates to a singleton populated by ABTestingServiceProvider.
 * Call Experiments::setInstance() in tests or when bootstrapping outside Laravel.
 */
final class Experiments
{
    private static ?self $instance = null;

    public function __construct(
        private readonly ExperimentRegistry $registry,
        private readonly FeatureFlagRegistry $flagRegistry,
        private readonly ResolvesVariant $resolver,
        private readonly EventSink $eventSink,
        private readonly AssignmentRepository $assignmentRepository,
        private readonly BucketingStrategy $bucketingStrategy,
    ) {
        //
    }

    // -------------------------------------------------------------------------
    // Static façade
    // -------------------------------------------------------------------------

    /**
     * Bind the singleton used by all static calls. Called by the service
     * provider on boot; override in tests to inject fakes.
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Return the current singleton, throwing if the service provider has not
     * been registered.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new RuntimeException(
                'Experiments has not been initialised. ' .
                'Ensure ABTests\ABTestingServiceProvider is registered.'
            );
        }

        return self::$instance;
    }

    /**
     * Create a per-unit resolver handle. All variant lookups and event
     * recording for a single unit in a single request flow through this object.
     */
    public static function for(Bucketable $unit): ExperimentResolver
    {
        $self = self::getInstance();

        return new ExperimentResolver(
            unit: $unit,
            registry: $self->registry,
            resolver: $self->resolver,
            eventSink: $self->eventSink,
            assignmentRepository: $self->assignmentRepository,
        );
    }

    /**
     * Resolve a feature flag value for the given unit.
     *
     * Checks the flag's operational state (enabled, rollout %, kill switch),
     * builds a deterministic Context, and delegates to the flag's resolve() method.
     * Returns the flag's default value on any failure so callers never crash.
     *
     * Usage:
     *
     *   $showNewCheckout = Experiments::flag(NewCheckoutFlag::class, $user);
     *
     * @param class-string<FeatureFlag> $flagClass
     */
    public static function flag(string $flagClass, Bucketable $unit): mixed
    {
        return self::getInstance()->resolveFlag($flagClass, $unit);
    }

    /**
     * Convenience static shorthand for tracking a metric event.
     *
     * @param class-string<Metric>|string $metricClassOrKey
     */
    public static function track(
        string $metricClassOrKey,
        Bucketable $for,
        ?float $value = null,
    ): void {
        self::for($for)->track($metricClassOrKey, $value);
    }

    /**
     * Record a pre-experiment covariate value for CUPED variance reduction.
     *
     * Call this for each unit before the experiment starts (e.g. via a nightly
     * job that computes last-30-day metric values). The analysis engine will
     * automatically use these values to reduce variance when results are computed.
     *
     * @param class-string<Metric>|string $metricClassOrKey
     */
    public static function recordCovariate(
        string $metricClassOrKey,
        string $experimentKey,
        string $unitType,
        string $unitKey,
        float $value,
    ): void {
        // Resolve class-string to key if needed.
        $metricKey = $metricClassOrKey;

        if (class_exists($metricClassOrKey)) {
            try {
                $reflector = new \ReflectionClass($metricClassOrKey);
                $attrs = $reflector->getAttributes(AsMetric::class);

                if ($attrs !== []) {
                    $metricKey = $attrs[0]->newInstance()->key;
                }
            } catch (\Throwable) {
                // Keep the class-string as-is.
            }
        }

        app(RecordCovariateCommandHandler::class)->handle(
            new RecordCovariateCommand(
                experimentKey: $experimentKey,
                metricKey: $metricKey,
                unitType: $unitType,
                unitKey: $unitKey,
                value: $value,
            )
        );
    }

    /**
     * Right-to-erasure (GDPR forget). Deletes all events and assignments for the
     * given unit across every experiment and returns the row counts deleted.
     * Rollup figures will self-correct on the next RefreshRollupsJob cycle.
     *
     * @return array{deleted_events: int, deleted_assignments: int}
     */
    public static function forget(
        string $unitType,
        string $unitKey,
        string $actorIdentifier = 'system',
    ): array {
        return app(ForgetUnitCommandHandler::class)->handle(
            new ForgetUnitCommand(
                unitType: $unitType,
                unitKey: $unitKey,
                actorIdentifier: $actorIdentifier,
            )
        );
    }

    // -------------------------------------------------------------------------
    // Instance accessors (useful when injected via the container)
    // -------------------------------------------------------------------------

    public function registry(): ExperimentRegistry
    {
        return $this->registry;
    }

    public function flagRegistry(): FeatureFlagRegistry
    {
        return $this->flagRegistry;
    }

    public function resolver(): ResolvesVariant
    {
        return $this->resolver;
    }

    // -------------------------------------------------------------------------
    // Private — flag resolution
    // -------------------------------------------------------------------------

    /**
     * @param class-string<FeatureFlag> $flagClass
     */
    private function resolveFlag(string $flagClass, Bucketable $unit): mixed
    {
        try {
            $definition = $this->flagRegistry->findByClass($flagClass);
        } catch (Throwable) {
            // Not registered — instantiate to get the default from the attribute.
            return $this->defaultForFlagClass($flagClass);
        }

        // Check operational state persisted in the database.
        $state = FeatureFlagStateModel::query()->firstWhere('key', $definition->key);

        if ($state === null || ! $state->is_enabled || $state->killed_at !== null) {
            return $definition->defaultValue;
        }

        // Evaluate targeting conditions (attribute-based AND logic) before
        // computing the bucketing position, since attribute checks are cheap.
        if (! $this->unitMatchesConditions($unit, $state->conditions ?? [])) {
            return $definition->defaultValue;
        }

        // Stamp the last evaluation time for stale-flag detection. We do this
        // before the rollout gate so any qualifying resolution counts.
        $state->updateQuietly(['last_evaluated_at' => Carbon::now()]);

        // Compute a stable position and build the resolution context.
        $position = $this->bucketingStrategy->position($definition->key, $unit);

        // Apply rollout gating: if unit falls outside the rollout slice, return default.
        if ($position >= ($state->rollout_percentage / 100)) {
            return $definition->defaultValue;
        }

        $environment = Environment::tryFrom((string) app()->environment())
            ?? Environment::production;

        $context = new Context(
            unit: $unit,
            environment: $environment,
            position: $position,
        );

        try {
            return new $flagClass()->resolve($context);
        } catch (Throwable) {
            return $definition->defaultValue;
        }
    }

    /**
     * Deserializes a stored conditions array into Criterion objects and
     * evaluates them as a conjunction (AND) against the unit's attributes.
     * Returns true when there are no conditions (open targeting).
     *
     * @param list<array{attribute: string, operator: string, expected: mixed}> $conditions
     */
    private function unitMatchesConditions(Bucketable $unit, array $conditions): bool
    {
        if ($conditions === []) {
            return true;
        }

        $segment = array_reduce(
            $conditions,
            static function (Segment $carry, array $raw): Segment {
                $operator = Operator::tryFrom($raw['operator'] ?? '') ?? Operator::equals;

                return $carry->and(
                    attribute: $raw['attribute'],
                    value: $raw['expected'],
                    operator: $operator,
                );
            },
            Segment::any(),
        );

        return $segment->matches($unit);
    }

    /**
     * @param class-string<FeatureFlag> $flagClass
     */
    private function defaultForFlagClass(string $flagClass): mixed
    {
        try {
            $reflector = new \ReflectionClass($flagClass);
            $attrs = $reflector->getAttributes(AsFeatureFlag::class);

            if ($attrs !== []) {
                return $attrs[0]->newInstance()->defaultValue;
            }
        } catch (Throwable) {
            // Swallow — returning false is the safe fallback.
        }

        return false;
    }
}
