<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\StatisticalEngine;

/**
 * The normalized analysis settings for an experiment, regardless of whether
 * they came from an #[Analysis] attribute or a database row.
 */
final readonly class AnalysisConfiguration
{
    public function __construct(
        public StatisticalEngine $engine,
        public Confidence $confidence,
        public bool $sequential,
    ) {
        //
    }

    public static function default(): self
    {
        return new self(
            engine: StatisticalEngine::both,
            confidence: new Confidence(0.95),
            sequential: true,
        );
    }
}
