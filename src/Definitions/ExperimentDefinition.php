<?php

declare(strict_types=1);

namespace ABTests\Definitions;

use ABTests\Enums\MetricRole;
use ABTests\Values\Allocation;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\Segment;
use LogicException;

/**
 * The normalized, framework-agnostic representation of an experiment. This is
 * the contract between the front-ends and the engine: the attribute reader
 * produces one of these from a decorated class, and a database loader produces
 * the same thing from dashboard-created rows. Everything downstream (resolver,
 * analysis, dashboard) consumes only this, never the original source.
 */
final readonly class ExperimentDefinition
{
    /**
     * @param  string  $unitType  The unit's stable type key (e.g. "tenant").
     * @param  list<MetricBinding>  $metrics
     */
    /**
     * @param  list<string>|null  $allowedEnvironments  null = all environments (no restriction).
     */
    public function __construct(
        public string $key,
        public string $unitType,
        public Allocation $allocation,
        public AnalysisConfiguration $analysis,
        public Segment $audience,
        public array $metrics,
        public ?string $name = null,
        public ?string $layer = null,
        public ?array $allowedEnvironments = null,
    ) {}

    public function primaryMetric(): MetricBinding
    {
        foreach ($this->metrics as $metric) {
            if ($metric->role === MetricRole::primary) {
                return $metric;
            }
        }

        throw new LogicException("Experiment [$this->key] has no primary metric.");
    }

    /**
     * @return list<MetricBinding>
     */
    public function guardrails(): array
    {
        return array_values(array_filter(
            $this->metrics,
            static fn (MetricBinding $metric): bool => $metric->role === MetricRole::guardrail,
        ));
    }
}
