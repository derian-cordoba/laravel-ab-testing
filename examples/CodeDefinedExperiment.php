<?php

declare(strict_types=1);

/**
 * Front-end 1: code-defined (typed) experiment. Maximum compile-time safety.
 * Everything here is the consumer's own application code. Self-contained so the
 * relationships are unambiguous.
 */

namespace App\Experiments;

use ABTests\Attributes\Analysis;
use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\AsMetric;
use ABTests\Attributes\AsUnit;
use ABTests\Attributes\Control;
use ABTests\Attributes\Guardrail;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Attributes\Weight;
use ABTests\Concerns\IsVariant;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\Variant;
use ABTests\Enums\Aggregate;
use ABTests\Enums\MetricType;
use ABTests\Enums\Operator;
use ABTests\Enums\StatisticalEngine;
use ABTests\Experiment;
use ABTests\Metric;
use ABTests\Values\Segment;

/** The arms: a backed enum is the exhaustively matchable source of truth. */
enum ButtonColor: string implements Variant
{
    use IsVariant;

    #[Control]
    #[Weight(50)]
    case Control = 'control';

    #[Weight(25)]
    case Green = 'green';

    #[Weight(25)]
    case Blue = 'blue';
}

/** The assignment unit: this experiment buckets per tenant, not per user. */
#[AsUnit(key: 'tenant')]
final readonly class TenantUnit implements Bucketable
{
    /** @param object{id: int|string, plan: string, country: string, seats: int} $tenant */
    public function __construct(private object $tenant) {}

    public function bucketingKey(): string
    {
        return "tenant:{$this->tenant->id}";
    }

    public function attributes(): array
    {
        return [
            'plan' => $this->tenant->plan,
            'country' => $this->tenant->country,
            'seats' => $this->tenant->seats,
        ];
    }
}

#[AsMetric(key: 'checkout-conversion', type: MetricType::binary, event: 'checkout.completed')]
final class CheckoutConversion extends Metric {}

#[AsMetric(key: 'error-rate', type: MetricType::continuous, event: 'request.failed', aggregate: Aggregate::average)]
final class ErrorRate extends Metric {}

/** The experiment: all structure declared, no magic strings anywhere. */
#[AsExperiment(
    key: 'checkout-button-color',
    unit: TenantUnit::class,
    variants: ButtonColor::class,
    name: 'Checkout button colour',
    layer: 'checkout',
)]
#[PrimaryMetric(CheckoutConversion::class)]
#[Guardrail(ErrorRate::class, maximumRegression: 0.005)]
#[Analysis(engine: StatisticalEngine::both, confidenceLevel: 0.95, sequential: true)]
final class CheckoutButtonColor extends Experiment
{
    public function audience(): Segment
    {
        return Segment::where('plan', 'pro')
            ->and('country', ['US', 'CA'], Operator::in);
    }
}

/*
 | Resolution at the call site (target ergonomics):
 |
 |   $variant = Experiments::for($tenant)->variant(CheckoutButtonColor::class);
 |
 |   return match ($variant) {
 |       ButtonColor::Green   => view('checkout.green'),
 |       ButtonColor::Blue    => view('checkout.blue'),
 |       ButtonColor::Control => view('checkout.default'),
 |   };
 |
 |   Experiments::track(CheckoutConversion::class, for: $tenant);
 */
