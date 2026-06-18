<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for VariantsController.
 *
 * Covers: adding, updating, and removing variant arms.
 */
final class VariantsControllerTest extends FeatureTestCase
{
    private ExperimentModel $experiment;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var ExperimentModel $experiment */
        $experiment = ExperimentModel::query()->create([
            'key' => 'checkout-button-color',
            'status' => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed' => false,
        ]);

        $this->experiment = $experiment;
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/variants — store
    // ------------------------------------------------------------------

    #[Test]
    public function store_adds_a_control_variant(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.variants.store', ['key' => 'checkout-button-color']), [
            'key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);

        $response->assertStatus(200);

        $variants = $response->json('data.attributes.variants');
        $this->assertCount(1, $variants);
        $this->assertSame('control', $variants[0]['key']);
        $this->assertSame(50, $variants[0]['weight']);
        $this->assertTrue($variants[0]['is_control']);
    }

    #[Test]
    public function store_adds_a_treatment_variant(): void
    {
        VariantModel::query()->create([
            'experiment_id' => $this->experiment->id,
            'key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.variants.store', ['key' => 'checkout-button-color']), [
            'key' => 'green',
            'weight' => 50,
        ]);

        $response->assertStatus(200);

        $variants = $response->json('data.attributes.variants');
        $this->assertCount(2, $variants);
    }

    #[Test]
    public function store_returns_404_for_unknown_experiment(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.variants.store', ['key' => 'nonexistent']), [
            'key' => 'control',
            'weight' => 100,
        ]);

        $response->assertStatus(404);
    }

    #[Test]
    public function store_returns_422_when_key_is_missing(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.variants.store', ['key' => 'checkout-button-color']), [
            'weight' => 50,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The key field is required.');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/key');
    }

    #[Test]
    public function store_returns_422_when_weight_is_missing(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.variants.store', ['key' => 'checkout-button-color']), [
            'key' => 'control',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The weight field is required.');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/weight');
    }

    #[Test]
    public function store_returns_422_when_weight_exceeds_100(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.variants.store', ['key' => 'checkout-button-color']), [
            'key' => 'control',
            'weight' => 150,
        ]);

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // PUT /experiments/{key}/variants/{id} — update
    // ------------------------------------------------------------------

    #[Test]
    public function update_changes_variant_weight(): void
    {
        $variant = VariantModel::query()->create([
            'experiment_id' => $this->experiment->id,
            'key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);

        VariantModel::query()->create([
            'experiment_id' => $this->experiment->id,
            'key' => 'green',
            'weight' => 50,
            'is_control' => false,
        ]);

        $response = $this->putJson(
            route('ab-testing.api.v1.experiments.variants.update', ['key' => 'checkout-button-color', 'id' => $variant->id]),
            ['key' => 'control', 'weight' => 60, 'is_control' => true],
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('ab_testing_variants', [
            'id' => $variant->id,
            'weight' => 60,
        ]);
    }

    #[Test]
    public function update_returns_404_for_variant_belonging_to_different_experiment(): void
    {
        // Create another experiment and variant.
        /** @var ExperimentModel $otherExperiment */
        $otherExperiment = ExperimentModel::query()->create([
            'key' => 'other-experiment', 'status' => ExperimentStatus::draft->value,
            'traffic_percentage' => 0, 'is_killed' => false,
        ]);

        $otherVariant = VariantModel::query()->create([
            'experiment_id' => $otherExperiment->id,
            'key' => 'control',
            'weight' => 100,
            'is_control' => true,
        ]);

        // Try to update the other experiment's variant via checkout-button-color path.
        $response = $this->putJson(
            route('ab-testing.api.v1.experiments.variants.update', ['key' => 'checkout-button-color', 'id' => $otherVariant->id]),
            ['key' => 'control', 'weight' => 100, 'is_control' => true],
        );

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // DELETE /experiments/{key}/variants/{id} — destroy
    // ------------------------------------------------------------------

    #[Test]
    public function destroy_removes_a_treatment_variant(): void
    {
        VariantModel::query()->create([
            'experiment_id' => $this->experiment->id,
            'key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);

        $treatment = VariantModel::query()->create([
            'experiment_id' => $this->experiment->id,
            'key' => 'green',
            'weight' => 50,
            'is_control' => false,
        ]);

        $response = $this->deleteJson(
            route('ab-testing.api.v1.experiments.variants.destroy', ['key' => 'checkout-button-color', 'id' => $treatment->id]),
        );

        $response->assertStatus(204);

        $this->assertDatabaseMissing('ab_testing_variants', [
            'id' => $treatment->id,
        ]);
    }

    #[Test]
    public function destroy_returns_404_for_unknown_variant(): void
    {
        $response = $this->deleteJson(
            route('ab-testing.api.v1.experiments.variants.destroy', ['key' => 'checkout-button-color', 'id' => 99999]),
        );

        $response->assertStatus(404);
    }
}
