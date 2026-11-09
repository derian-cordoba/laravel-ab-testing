<?php

declare(strict_types=1);

namespace ABTests\Providers;

use ABTests\Application\ResultsService;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Contracts\CovariateRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Analysis\AnalysisService;
use ABTests\Domain\Analysis\BayesianAnalysisEngine;
use ABTests\Domain\Analysis\FrequentistAnalysisEngine;
use ABTests\Domain\Analysis\SampleRatioMismatchDetector;
use ABTests\Domain\Analysis\VerdictResolver;
use Illuminate\Support\ServiceProvider;

/**
 * Binds all statistical-analysis services as singletons.
 *
 * Covered bindings:
 *   FrequentistAnalysisEngine  — two-sample z-test (fixed-horizon + mSPRT)
 *   BayesianAnalysisEngine     — normal-normal conjugate
 *   SampleRatioMismatchDetector
 *   VerdictResolver            — maps analysis results to ship/doNotShip/inconclusive
 *   AnalysisService            — orchestrates both engines + CUPED + SRM
 *   ResultsService             — CQRS read side for the dashboard
 */
final class AnalysisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
                covariateRepository: $this->app->make(CovariateRepository::class),
            );
        });

        $this->app->singleton(ResultsService::class, function (): ResultsService {
            return new ResultsService(
                registry: $this->app->make(ExperimentRegistry::class),
                analysisService: $this->app->make(AnalysisService::class),
                experimentRepository: $this->app->make(ExperimentRepository::class),
            );
        });
    }
}
