<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for the ExperimentsController (CRUD endpoints).
 *
 * Base URL: route('ab-testing.api.v1.experiments.*')
 */
final class ExperimentsControllerTest extends FeatureTestCase
{
    // ------------------------------------------------------------------
    // GET /experiments — index
    // ------------------------------------------------------------------

    #[Test]
    public function index_returns_empty_list_when_no_experiments_exist(): void
    {
        $response = $this->getJson(route('ab-testing.api.v1.experiments.index'));

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
    }

    #[Test]
    public function index_returns_paginated_list_of_experiments(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'checkout-button-color',
            'name'               => 'Checkout Button Color',
            'status'             => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed'          => false,
        ]);

        ExperimentModel::query()->create([
            'key'                => 'pricing-layout',
            'name'               => 'Pricing Layout',
            'status'             => ExperimentStatus::running->value,
            'traffic_percentage' => 50,
            'is_killed'          => false,
        ]);

        $response = $this->getJson(route('ab-testing.api.v1.experiments.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', 'pricing-layout'); // ordered by updated_at desc
        $response->assertJsonPath('data.1.id', 'checkout-button-color');
    }

    #[Test]
    public function index_filters_by_status(): void
    {
        ExperimentModel::query()->create([
            'key' => 'experiment-a', 'status' => ExperimentStatus::draft->value, 'is_killed' => false, 'traffic_percentage' => 0,
        ]);
        ExperimentModel::query()->create([
            'key' => 'experiment-b', 'status' => ExperimentStatus::running->value, 'is_killed' => false, 'traffic_percentage' => 100,
        ]);

        $response = $this->getJson(route('ab-testing.api.v1.experiments.index', ['status' => 'running']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 'experiment-b');
    }

    // ------------------------------------------------------------------
    // GET /experiments/{key} — show
    // ------------------------------------------------------------------

    #[Test]
    public function show_returns_experiment_with_variants(): void
    {
        $model = ExperimentModel::query()->create([
            'key'                => 'checkout-button-color',
            'name'               => 'Checkout Button Color',
            'status'             => ExperimentStatus::draft->value,
            'traffic_percentage' => 0,
            'is_killed'          => false,
        ]);

        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'control', 'weight' => 50, 'is_control' => true]);
        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'green',   'weight' => 50, 'is_control' => false]);

        $response = $this->getJson(route('ab-testing.api.v1.experiments.show', ['key' => 'checkout-button-color']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', 'checkout-button-color');
        $response->assertJsonPath('data.attributes.name', 'Checkout Button Color');
        $response->assertJsonPath('data.attributes.status', 'draft');
        $response->assertJsonCount(2, 'data.attributes.variants');
    }

    #[Test]
    public function show_returns_404_for_unknown_key(): void
    {
        $response = $this->getJson(route('ab-testing.api.v1.experiments.show', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /experiments — store
    // ------------------------------------------------------------------

    #[Test]
    public function store_creates_experiment_in_draft_status(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.store'), [
            'key'                => 'checkout-button-color',
            'name'               => 'Checkout Button Color',
            'layer'              => 'checkout',
            'traffic_percentage' => 0,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.id', 'checkout-button-color');
        $response->assertJsonPath('data.attributes.name', 'Checkout Button Color');
        $response->assertJsonPath('data.attributes.status', 'draft');
        $response->assertJsonPath('data.attributes.layer', 'checkout');

        $this->assertDatabaseHas('ab_testing_experiments', [
            'key'    => 'checkout-button-color',
            'status' => 'draft',
        ]);
    }

    #[Test]
    public function store_returns_422_when_key_is_missing(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.store'), [
            'name' => 'No Key Experiment',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The key field is required.');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/key');
    }

    #[Test]
    public function store_creates_experiment_without_optional_fields(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.store'), [
            'key' => 'minimal-experiment',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.id', 'minimal-experiment');
        $response->assertJsonPath('data.attributes.name', null);
        $response->assertJsonPath('data.attributes.layer', null);
    }

    // ------------------------------------------------------------------
    // PUT /experiments/{key} — update
    // ------------------------------------------------------------------

    #[Test]
    public function update_changes_editable_metadata(): void
    {
        ExperimentModel::query()->create([
            'key'    => 'checkout-button-color',
            'status' => ExperimentStatus::draft->value,
            'is_killed' => false,
            'traffic_percentage' => 0,
        ]);

        $response = $this->putJson(route('ab-testing.api.v1.experiments.update', ['key' => 'checkout-button-color']), [
            'name'               => 'New Name',
            'target_sample_size' => 5000,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.name', 'New Name');

        $this->assertDatabaseHas('ab_testing_experiments', [
            'key'                => 'checkout-button-color',
            'name'               => 'New Name',
            'target_sample_size' => 5000,
        ]);
    }

    #[Test]
    public function update_returns_404_for_unknown_key(): void
    {
        $response = $this->putJson(route('ab-testing.api.v1.experiments.update', ['key' => 'nonexistent']), [
            'name' => 'New Name',
        ]);

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // DELETE /experiments/{key} — destroy (archive)
    // ------------------------------------------------------------------

    #[Test]
    public function destroy_archives_a_completed_experiment(): void
    {
        ExperimentModel::query()->create([
            'key'                => 'checkout-button-color',
            'status'             => ExperimentStatus::completed->value,
            'is_killed'          => false,
            'traffic_percentage' => 100,
        ]);

        $response = $this->deleteJson(route('ab-testing.api.v1.experiments.destroy', ['key' => 'checkout-button-color']));

        $response->assertStatus(204);

        $this->assertDatabaseHas('ab_testing_experiments', [
            'key'    => 'checkout-button-color',
            'status' => ExperimentStatus::archived->value,
        ]);
    }

    #[Test]
    public function destroy_returns_404_for_unknown_key(): void
    {
        $response = $this->deleteJson(route('ab-testing.api.v1.experiments.destroy', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    #[Test]
    public function gate_denies_access_when_defined_and_returning_false(): void
    {
        Gate::define('manageAbTestingApi', static fn (): bool => false);

        $response = $this->getJson(route('ab-testing.api.v1.experiments.index'));

        // In testing env (non-production) returns 403; in production returns 404.
        $response->assertStatus(403);
    }

    #[Test]
    public function gate_allows_access_when_not_defined(): void
    {
        // Gate is not defined by default — middleware short-circuits and allows.
        $response = $this->getJson(route('ab-testing.api.v1.experiments.index'));

        $response->assertStatus(200);
    }
}
