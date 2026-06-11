<?php

declare(strict_types=1);

namespace ABTests;

use RuntimeException;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\Variant;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Resolution\Resolver;

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
        private readonly Resolver $resolver,
        private readonly EventSink $eventSink,
        private readonly AssignmentRepository $assignmentRepository,
    ) {
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

    // -------------------------------------------------------------------------
    // Instance accessors (useful when injected via the container)
    // -------------------------------------------------------------------------

    public function registry(): ExperimentRegistry
    {
        return $this->registry;
    }

    public function resolver(): Resolver
    {
        return $this->resolver;
    }
}
