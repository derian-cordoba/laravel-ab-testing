<?php

declare(strict_types=1);

namespace ABTests\Tests\Feature\Api;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Tests\Feature\FeatureTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for ExperimentLifecycleController.
 *
 * Covers: start, pause, resume, stop, traffic ramp, kill switch.
 */
final class ExperimentLifecycleControllerTest extends FeatureTestCase
{
    private function createExperiment(string $key, string $status, int $traffic = 100): ExperimentModel
    {
        /** @var ExperimentModel $model */
        $model = ExperimentModel::query()->create([
            'key'                => $key,
            'status'             => $status,
            'traffic_percentage' => $traffic,
            'is_killed'          => false,
        ]);

        // Add control + treatment variants so the experiment is valid.
        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'control', 'weight' => 50, 'is_control' => true]);
        VariantModel::query()->create(['experiment_id' => $model->id, 'key' => 'treatment', 'weight' => 50, 'is_control' => false]);

        return $model;
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/start
    // ------------------------------------------------------------------

    #[Test]
    public function start_transitions_draft_experiment_to_running(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::draft->value, 0);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.start', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.status', 'running');

        $this->assertDatabaseHas('ab_testing_experiments', [
            'key'    => 'my-experiment',
            'status' => 'running',
        ]);
    }

    #[Test]
    public function start_transitions_scheduled_experiment_to_running(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::scheduled->value, 50);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.start', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.status', 'running');
    }

    #[Test]
    public function start_sets_traffic_to_100_when_zero_before_start(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::draft->value, 0);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.start', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.traffic_percentage', 100);
    }

    #[Test]
    public function start_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.start', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/pause
    // ------------------------------------------------------------------

    #[Test]
    public function pause_transitions_running_experiment_to_paused(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::running->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.pause', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.status', 'paused');
    }

    #[Test]
    public function pause_returns_404_for_unknown_key(): void
    {
        $response = $this->postJson(route('ab-testing.api.v1.experiments.pause', ['key' => 'nonexistent']));

        $response->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/resume
    // ------------------------------------------------------------------

    #[Test]
    public function resume_transitions_paused_experiment_to_running(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::paused->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.resume', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.status', 'running');
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/stop
    // ------------------------------------------------------------------

    #[Test]
    public function stop_transitions_running_experiment_to_completed(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::running->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.stop', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.status', 'completed');
    }

    #[Test]
    public function stop_transitions_paused_experiment_to_completed(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::paused->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.stop', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.status', 'completed');
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/traffic
    // ------------------------------------------------------------------

    #[Test]
    public function traffic_updates_percentage_on_running_experiment(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::running->value, 10);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.traffic', ['key' => 'my-experiment']), [
            'traffic_percentage' => 50,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.traffic_percentage', 50);

        $this->assertDatabaseHas('ab_testing_experiments', [
            'key'                => 'my-experiment',
            'traffic_percentage' => 50,
        ]);
    }

    #[Test]
    public function traffic_returns_422_when_percentage_is_out_of_range(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::running->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.traffic', ['key' => 'my-experiment']), [
            'traffic_percentage' => 150,
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function traffic_returns_422_when_percentage_is_missing(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::running->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.traffic', ['key' => 'my-experiment']), []);

        $response->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // POST /experiments/{key}/kill-switch
    // ------------------------------------------------------------------

    #[Test]
    public function kill_switch_activates_when_is_killed_is_true(): void
    {
        $this->createExperiment('my-experiment', ExperimentStatus::running->value);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.kill-switch', ['key' => 'my-experiment']), [
            'is_killed' => true,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', true);

        $this->assertDatabaseHas('ab_testing_experiments', [
            'key'       => 'my-experiment',
            'is_killed' => true,
        ]);
    }

    #[Test]
    public function kill_switch_deactivate_endpoint_clears_kill_switch(): void
    {
        $model = $this->createExperiment('my-experiment', ExperimentStatus::running->value);
        $model->update(['is_killed' => true]);

        $response = $this->postJson(route('ab-testing.api.v1.experiments.kill-switch.deactivate', ['key' => 'my-experiment']));

        $response->assertStatus(200);
        $response->assertJsonPath('data.attributes.is_killed', false);
    }

    // ------------------------------------------------------------------
    // Full lifecycle: draft → running → paused → running → completed → archived
    // ------------------------------------------------------------------

    #[Test]
    public function full_lifecycle_transitions_succeed_in_order(): void
    {
        $this->createExperiment('lifecycle-test', ExperimentStatus::draft->value, 0);

        $this->postJson(route('ab-testing.api.v1.experiments.start', ['key' => 'lifecycle-test']))->assertStatus(200);
        $this->assertDatabaseHas('ab_testing_experiments', ['key' => 'lifecycle-test', 'status' => 'running']);

        $this->postJson(route('ab-testing.api.v1.experiments.pause', ['key' => 'lifecycle-test']))->assertStatus(200);
        $this->assertDatabaseHas('ab_testing_experiments', ['key' => 'lifecycle-test', 'status' => 'paused']);

        $this->postJson(route('ab-testing.api.v1.experiments.resume', ['key' => 'lifecycle-test']))->assertStatus(200);
        $this->assertDatabaseHas('ab_testing_experiments', ['key' => 'lifecycle-test', 'status' => 'running']);

        $this->postJson(route('ab-testing.api.v1.experiments.stop', ['key' => 'lifecycle-test']))->assertStatus(200);
        $this->assertDatabaseHas('ab_testing_experiments', ['key' => 'lifecycle-test', 'status' => 'completed']);

        $this->deleteJson(route('ab-testing.api.v1.experiments.destroy', ['key' => 'lifecycle-test']))->assertStatus(204);
        $this->assertDatabaseHas('ab_testing_experiments', ['key' => 'lifecycle-test', 'status' => 'archived']);
    }
}
