<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for FeatureFlagsController (CRUD endpoints).
 *
 * Base route name: ab-testing.api.v1.feature-flags.*
 */
final class FeatureFlagsControllerTest extends FeatureTestCase
{
    private function createFlag(string $key, bool $isEnabled = false, int $rolloutPercentage = 100): FeatureFlagStateModel
    {
        /** @var FeatureFlagStateModel $model */
        $model = FeatureFlagStateModel::query()->create([
            'key'                => $key,
            'is_enabled'         => $isEnabled,
            'rollout_percentage' => $rolloutPercentage,
        ]);

        return $model;
    }

    // ------------------------------------------------------------------
    // GET /feature-flags — index
    // ------------------------------------------------------------------

    #[Test]
    public function index_returns_empty_list_when_no_flags_exist(): void
    {
        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.index'));

        $response->assertStatus(200);
        $response->assertJsonPath('data', []);
    }

    #[Test]
    public function index_returns_paginated_list_of_flags(): void
    {
        $this->createFlag('dark-mode', true);
        $this->createFlag('new-checkout', false);

        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.index'));

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.type', 'feature-flags');
    }

    #[Test]
    public function index_filters_by_is_enabled_true(): void
    {
        $this->createFlag('dark-mode', true);
        $this->createFlag('new-checkout', false);

        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.index', ['is_enabled' => '1']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 'dark-mode');
    }

    #[Test]
    public function index_filters_by_is_enabled_false(): void
    {
        $this->createFlag('dark-mode', true);
        $this->createFlag('new-checkout', false);

        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.index', ['is_enabled' => '0']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', 'new-checkout');
    }

    // ------------------------------------------------------------------
    // GET /feature-flags/{key} — show
    // ------------------------------------------------------------------

    #[Test]
    public function show_returns_flag_resource(): void
    {
        $this->createFlag('dark-mode', true, 50);

        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.show', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', 'dark-mode');
        $response->assertJsonPath('data.type', 'feature-flags');
        $response->assertJsonPath('data.attributes.is_enabled', true);
        $response->assertJsonPath('data.attributes.rollout_percentage', 50);
        $response->assertJsonPath('data.attributes.is_killed', false);
        $response->assertJsonPath('data.attributes.conditions', []);
    }

    #[Test]
    public function show_returns_404_for_unknown_key(): void
    {
        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.show', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /feature-flags — store
    // ------------------------------------------------------------------

    #[Test]
    public function store_creates_flag_in_disabled_state_by_default(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.store'), [
            'key' => 'dark-mode',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.id', 'dark-mode');
        $response->assertJsonPath('data.type', 'feature-flags');
        $response->assertJsonPath('data.attributes.is_enabled', false);
        $response->assertJsonPath('data.attributes.rollout_percentage', 100);

        $this->assertDatabaseHas('ab_testing_feature_flag_states', [
            'key'        => 'dark-mode',
            'is_enabled' => false,
        ]);
    }

    #[Test]
    public function store_creates_flag_with_explicit_enabled_state_and_rollout(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.store'), [
            'key'                => 'new-checkout',
            'is_enabled'         => true,
            'rollout_percentage' => 25,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.attributes.is_enabled', true);
        $response->assertJsonPath('data.attributes.rollout_percentage', 25);

        $this->assertDatabaseHas('ab_testing_feature_flag_states', [
            'key'                => 'new-checkout',
            'is_enabled'         => true,
            'rollout_percentage' => 25,
        ]);
    }

    #[Test]
    public function store_returns_422_when_key_is_missing(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.store'), []);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The key field is required.');
        $response->assertJsonPath('errors.0.source.pointer', '/data/attributes/key');
    }

    #[Test]
    public function store_returns_422_when_rollout_percentage_is_out_of_range(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.store'), [
            'key'                => 'bad-flag',
            'rollout_percentage' => 150,
        ]);

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // DELETE /feature-flags/{key} — destroy
    // ------------------------------------------------------------------

    #[Test]
    public function destroy_removes_the_flag_record(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->deleteJson(route('ab-testing.api.v1.feature-flags.destroy', ['key' => 'dark-mode']));

        $response->assertStatus(204);

        $this->assertDatabaseMissing('ab_testing_feature_flag_states', ['key' => 'dark-mode']);
    }

    #[Test]
    public function destroy_returns_404_for_unknown_key(): void
    {
        $response = $this->deleteJson(route('ab-testing.api.v1.feature-flags.destroy', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    #[Test]
    public function gate_denies_access_when_defined_and_returning_false(): void
    {
        Gate::define('manageAbTestingApi', static fn (): bool => false);

        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.index'));

        $response->assertStatus(403);
    }

    #[Test]
    public function gate_allows_access_when_not_defined(): void
    {
        $response = $this->getJson(route('ab-testing.api.v1.feature-flags.index'));

        $response->assertStatus(200);
    }
}
