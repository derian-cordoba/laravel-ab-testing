<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Tests\Feature\FeatureTestCase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for FeatureFlagLifecycleController.
 *
 * Covers: enable, disable, rollout, kill-switch, conditions.
 */
final class FeatureFlagLifecycleControllerTest extends FeatureTestCase
{
    private function createFlag(string $key, bool $isEnabled = false, int $rolloutPercentage = 100): FeatureFlagStateModel
    {
        /** @var FeatureFlagStateModel $model */
        $model = FeatureFlagStateModel::query()->create([
            'key' => $key,
            'is_enabled' => $isEnabled,
            'rollout_percentage' => $rolloutPercentage,
        ]);

        return $model;
    }

    // ------------------------------------------------------------------
    // POST /feature-flags/{key}/enable
    // ------------------------------------------------------------------

    #[Test]
    public function enable_sets_is_enabled_to_true(): void
    {
        $this->createFlag('dark-mode', false);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.enable', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', 'dark-mode');
        $response->assertJsonPath('data.attributes.is_enabled', true);

        $this->assertDatabaseHas('ab_testing_feature_flag_states', [
            'key' => 'dark-mode',
            'is_enabled' => true,
        ]);
    }

    #[Test]
    public function enable_is_idempotent_when_flag_is_already_enabled(): void
    {
        $this->createFlag('dark-mode', true);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.enable', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_enabled', true);
    }

    #[Test]
    public function enable_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.enable', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /feature-flags/{key}/disable
    // ------------------------------------------------------------------

    #[Test]
    public function disable_sets_is_enabled_to_false(): void
    {
        $this->createFlag('dark-mode', true);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.disable', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_enabled', false);

        $this->assertDatabaseHas('ab_testing_feature_flag_states', [
            'key' => 'dark-mode',
            'is_enabled' => false,
        ]);
    }

    #[Test]
    public function disable_is_idempotent_when_flag_is_already_disabled(): void
    {
        $this->createFlag('dark-mode', false);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.disable', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_enabled', false);
    }

    #[Test]
    public function disable_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.disable', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /feature-flags/{key}/rollout
    // ------------------------------------------------------------------

    #[Test]
    public function rollout_updates_the_percentage(): void
    {
        $this->createFlag('dark-mode', true, 100);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.rollout', ['key' => 'dark-mode']), [
            'rollout_percentage' => 25,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.rollout_percentage', 25);

        $this->assertDatabaseHas('ab_testing_feature_flag_states', [
            'key' => 'dark-mode',
            'rollout_percentage' => 25,
        ]);
    }

    #[Test]
    public function rollout_accepts_zero_percent(): void
    {
        $this->createFlag('dark-mode', true, 50);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.rollout', ['key' => 'dark-mode']), [
            'rollout_percentage' => 0,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.rollout_percentage', 0);
    }

    #[Test]
    public function rollout_returns_422_when_percentage_is_missing(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.rollout', ['key' => 'dark-mode']), []);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The rollout percentage field is required.');
    }

    #[Test]
    public function rollout_returns_422_when_percentage_is_out_of_range(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.rollout', ['key' => 'dark-mode']), [
            'rollout_percentage' => 150,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function rollout_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.rollout', ['key' => 'nonexistent']), [
            'rollout_percentage' => 50,
        ]);

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /feature-flags/{key}/kill-switch
    // ------------------------------------------------------------------

    #[Test]
    public function kill_switch_activates_when_is_killed_is_true(): void
    {
        $this->createFlag('dark-mode', true);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch', ['key' => 'dark-mode']), [
            'is_killed' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', true);

        $this->assertDatabaseHas('ab_testing_feature_flag_states', ['key' => 'dark-mode'])
            ->assertDatabaseCount('ab_testing_feature_flag_states', 1);

        $flag = FeatureFlagStateModel::query()->where('key', 'dark-mode')->firstOrFail();
        $this->assertNotNull($flag->killed_at);
    }

    #[Test]
    public function kill_switch_defaults_to_activating_when_no_body_is_sent(): void
    {
        $this->createFlag('dark-mode', true);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', true);
    }

    #[Test]
    public function kill_switch_deactivates_when_is_killed_is_false(): void
    {
        $flag = $this->createFlag('dark-mode', true);
        $flag->update(['killed_at' => Carbon::now()]);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch', ['key' => 'dark-mode']), [
            'is_killed' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', false);

        $updated = FeatureFlagStateModel::query()->where('key', 'dark-mode')->firstOrFail();
        $this->assertNull($updated->killed_at);
    }

    #[Test]
    public function kill_switch_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch', ['key' => 'nonexistent']), [
            'is_killed' => true,
        ]);

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /feature-flags/{key}/kill-switch/deactivate
    // ------------------------------------------------------------------

    #[Test]
    public function deactivate_kill_switch_clears_killed_at(): void
    {
        $flag = $this->createFlag('dark-mode', true);
        $flag->update(['killed_at' => Carbon::now()]);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch.deactivate', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', false);

        $updated = FeatureFlagStateModel::query()->where('key', 'dark-mode')->firstOrFail();
        $this->assertNull($updated->killed_at);
    }

    #[Test]
    public function deactivate_kill_switch_is_idempotent_when_not_killed(): void
    {
        $this->createFlag('dark-mode', true);

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch.deactivate', ['key' => 'dark-mode']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', false);
    }

    // ------------------------------------------------------------------
    // POST /feature-flags/{key}/conditions
    // ------------------------------------------------------------------

    #[Test]
    public function conditions_sets_targeting_rules_with_default_all_logic(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.conditions', ['key' => 'dark-mode']), [
            'conditions' => [
                ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.conditions_logic', 'all');
        $response->assertJsonPath('data.attributes.conditions.0.attribute', 'plan');
        $response->assertJsonPath('data.attributes.conditions.0.operator', 'equals');
        $response->assertJsonPath('data.attributes.conditions.0.expected', 'pro');
    }

    #[Test]
    public function conditions_stores_any_logic_when_specified(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.conditions', ['key' => 'dark-mode']), [
            'conditions' => [
                ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
                ['attribute' => 'country', 'operator' => 'equals', 'expected' => 'US'],
            ],
            'conditions_logic' => 'any',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.conditions_logic', 'any');
        $response->assertJsonCount(2, 'data.attributes.conditions');
    }

    #[Test]
    public function conditions_returns_422_when_conditions_array_is_missing(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.conditions', ['key' => 'dark-mode']), []);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.detail', 'The conditions field is required.');
    }

    #[Test]
    public function conditions_returns_422_when_a_condition_is_missing_attribute(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.conditions', ['key' => 'dark-mode']), [
            'conditions' => [
                ['operator' => 'equals', 'expected' => 'pro'],
            ],
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function conditions_returns_422_for_invalid_logic_value(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.conditions', ['key' => 'dark-mode']), [
            'conditions' => [
                ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
            ],
            'conditions_logic' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function conditions_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.feature-flags.conditions', ['key' => 'nonexistent']), [
            'conditions' => [
                ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
            ],
        ]);

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // DELETE /feature-flags/{key}/conditions
    // ------------------------------------------------------------------

    #[Test]
    public function clear_conditions_removes_all_targeting_rules(): void
    {
        $flag = $this->createFlag('dark-mode');
        $flag->update([
            'conditions' => [['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro']],
        ]);

        $response = $this->deleteJson(route('ab-testing.api.v1.feature-flags.conditions.clear', ['key' => 'dark-mode']));

        $response->assertStatus(204);

        $updated = FeatureFlagStateModel::query()->where('key', 'dark-mode')->firstOrFail();
        $this->assertNull($updated->conditions);
    }

    #[Test]
    public function clear_conditions_is_idempotent_when_no_conditions_exist(): void
    {
        $this->createFlag('dark-mode');

        $response = $this->deleteJson(route('ab-testing.api.v1.feature-flags.conditions.clear', ['key' => 'dark-mode']));

        $response->assertStatus(204);
    }

    // ------------------------------------------------------------------
    // Full lifecycle: create → enable → rollout → kill → deactivate → disable
    // ------------------------------------------------------------------

    #[Test]
    public function full_lifecycle_transitions_succeed_in_order(): void
    {
        $this->postJson(route('ab-testing.api.v1.feature-flags.store'), ['key' => 'lifecycle-flag'])
            ->assertStatus(201)
            ->assertJsonPath('data.attributes.is_enabled', false);

        $this->postJson(route('ab-testing.api.v1.feature-flags.enable', ['key' => 'lifecycle-flag']))
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.is_enabled', true);

        $this->postJson(route('ab-testing.api.v1.feature-flags.rollout', ['key' => 'lifecycle-flag']), ['rollout_percentage' => 10])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.rollout_percentage', 10);

        $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch', ['key' => 'lifecycle-flag']), ['is_killed' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.is_killed', true);

        $this->postJson(route('ab-testing.api.v1.feature-flags.kill-switch.deactivate', ['key' => 'lifecycle-flag']))
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.is_killed', false);

        $this->postJson(route('ab-testing.api.v1.feature-flags.disable', ['key' => 'lifecycle-flag']))
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.is_enabled', false);

        $this->deleteJson(route('ab-testing.api.v1.feature-flags.destroy', ['key' => 'lifecycle-flag']))
            ->assertStatus(204);

        $this->assertDatabaseMissing('ab_testing_feature_flag_states', ['key' => 'lifecycle-flag']);
    }
}
