<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Experiment;

/**
 * Binds an experiment to a controller action. Middleware resolves the variant,
 * injects it into the method, and records the exposure when the response is
 * sent. This is the attribute-first ergonomics layer over the fluent API.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ResolvesExperiment
{
    /**
     * @param class-string<Experiment> $experiment
     */
    public function __construct(public string $experiment)
    {
    }
}
