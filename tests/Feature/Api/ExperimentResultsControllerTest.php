<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for ExperimentResultsController.
 *
 * Covers: GET /results and GET /verdict.
 * Tests use rollup rows to feed the analysis engine without raw events.
 *
 * All responses use JSON:API format:
 *   - data responses: { data: { id, type, attributes: { ... } } }
 *   - error responses: { errors: [ { status, title, detail, meta } ] }
 */
final class ExperimentResultsControllerTest extends FeatureTestCase
{
    // ------------------------------------------------------------------
    // GET /experiments/{key}/results
    // ------------------------------------------------------------------

    #[Test]
    public function results_returns_404_when_experiment_does_not_exist(): void
    {
        $response = $this->getJson('/api/ab-testing/experiments/nonexistent/results');

        $response->assertStatus(404);
    }

    #[Test]
    public function results_returns_json_api_error_when_no_rollup_data(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'checkout-button-color',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed'          => false,
        ]);

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/results');

        $response->assertStatus(404);
        $response->assertJsonStructure([
            'errors' => [
                '*' => ['status', 'title', 'detail', 'meta'],
            ],
        ]);
        $response->assertJsonPath('errors.0.status', '404');
        $response->assertJsonPath('errors.0.detail', 'No results available yet for this experiment.');
        $response->assertJsonPath('errors.0.meta.experiment_key', 'checkout-button-color');
    }

    #[Test]
    public function results_returns_structured_data_when_rollups_exist(): void
    {
        $this->seedExperimentWithRollups('checkout-button-color');

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/results');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'attributes' => [
                    'status',
                    'computed_at',
                    'total_units',
                    'srm',
                    'variants',
                ],
            ],
        ]);

        $response->assertJsonPath('data.id', 'checkout-button-color');
        $response->assertJsonPath('data.type', 'experiment-results');
        $response->assertJsonPath('data.attributes.status', ExperimentStatus::running->value);
    }

    #[Test]
    public function results_includes_per_variant_primary_metric_stats(): void
    {
        $this->seedExperimentWithRollups('checkout-button-color');

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/results');

        $response->assertStatus(200);

        $variants = $response->json('data.attributes.variants');
        $this->assertCount(2, $variants);

        $keys = array_column($variants, 'key');
        $this->assertContains('control', $keys);
        $this->assertContains('green', $keys);
    }

    #[Test]
    public function results_includes_srm_detection_field(): void
    {
        $this->seedExperimentWithRollups('checkout-button-color');

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/results');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'attributes' => [
                    'srm' => ['detected', 'chi_square', 'p_value'],
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // GET /experiments/{key}/verdict
    // ------------------------------------------------------------------

    #[Test]
    public function verdict_returns_404_when_experiment_does_not_exist(): void
    {
        $response = $this->getJson('/api/ab-testing/experiments/nonexistent/verdict');

        $response->assertStatus(404);
    }

    #[Test]
    public function verdict_returns_inconclusive_when_no_rollup_data(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'checkout-button-color',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 100,
            'is_killed'          => false,
        ]);

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/verdict');

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', 'checkout-button-color');
        $response->assertJsonPath('data.type', 'experiment-verdicts');
        $response->assertJsonPath('data.attributes.overall_recommendation', 'inconclusive');
        $response->assertJsonPath('data.attributes.variants', []);
    }

    #[Test]
    public function verdict_contains_required_ci_cd_fields(): void
    {
        $this->seedExperimentWithRollups('checkout-button-color');

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/verdict');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'type',
                'attributes' => [
                    'status',
                    'srm_detected',
                    'overall_recommendation',
                    'computed_at',
                    'total_units',
                    'active_guardrail_breaches',
                    'variants' => [
                        '*' => [
                            'key',
                            'recommendation',
                            'label',
                            'count_of_units',
                            'conversion_rate',
                        ],
                    ],
                ],
            ],
        ]);
    }

    #[Test]
    public function verdict_marks_inconclusive_when_srm_is_detected(): void
    {
        // Plant a large ratio mismatch (1000 vs 100) to guarantee SRM detection.
        $model = ExperimentModel::query()->create([
            'key'                => 'srm-experiment',
            'status'             => ExperimentStatus::completed->value,
            'traffic_percentage' => 100,
            'is_killed'          => false,
        ]);

        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'control',   'weight' => 50, 'is_control' => true]);
        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'treatment', 'weight' => 50, 'is_control' => false]);

        // Severely imbalanced rollups trigger SRM.
        RollupModel::query()->create([
            'experiment_key'        => 'srm-experiment',
            'variant_key'           => 'control',
            'metric_key'            => 'checkout-conversion',
            'count_of_units'        => 1000,
            'conversions'           => 100,
            'sum_of_values'         => 100.0,
            'sum_of_squared_values' => 100.0,
        ]);

        RollupModel::query()->create([
            'experiment_key'        => 'srm-experiment',
            'variant_key'           => 'treatment',
            'metric_key'            => 'checkout-conversion',
            'count_of_units'        => 100,   // 10x fewer — severe mismatch
            'conversions'           => 50,
            'sum_of_values'         => 50.0,
            'sum_of_squared_values' => 50.0,
        ]);

        $response = $this->getJson('/api/ab-testing/experiments/srm-experiment/verdict');

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.srm_detected', true);
        $response->assertJsonPath('data.attributes.overall_recommendation', 'inconclusive');
    }

    #[Test]
    public function verdict_returns_non_empty_variants_for_completed_experiment_with_data(): void
    {
        $this->seedExperimentWithRollups('checkout-button-color', ExperimentStatus::completed->value);

        $response = $this->getJson('/api/ab-testing/experiments/checkout-button-color/verdict');

        $response->assertStatus(200);

        $variants = $response->json('data.attributes.variants');
        $this->assertNotEmpty($variants);

        // Treatment variant should have a verdict.
        $treatment = collect($variants)->firstWhere('key', 'green');
        $this->assertNotNull($treatment);
        $this->assertArrayHasKey('recommendation', $treatment);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Creates an experiment with two balanced rollup rows so the analysis
     * engine has sufficient statistics to produce a verdict.
     */
    private function seedExperimentWithRollups(
        string $key,
        string $status = ExperimentStatus::running->value,
    ): ExperimentModel {
        /** @var ExperimentModel $model */
        $model = ExperimentModel::query()->create([
            'key'                => $key,
            'status'             => $status,
            'traffic_percentage' => 100,
            'is_killed'          => false,
        ]);

        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'control', 'weight' => 50, 'is_control' => true]);
        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'green',   'weight' => 50, 'is_control' => false]);

        // Balanced, realistic rollup data (500 units per arm, ~10% conversion).
        RollupModel::query()->create([
            'experiment_key'        => $key,
            'variant_key'           => 'control',
            'metric_key'            => 'checkout-conversion',
            'count_of_units'        => 500,
            'conversions'           => 50,
            'sum_of_values'         => 50.0,
            'sum_of_squared_values' => 50.0,
        ]);

        RollupModel::query()->create([
            'experiment_key'        => $key,
            'variant_key'           => 'green',
            'metric_key'            => 'checkout-conversion',
            'count_of_units'        => 500,
            'conversions'           => 60,
            'sum_of_values'         => 60.0,
            'sum_of_squared_values' => 60.0,
        ]);

        return $model;
    }
}
