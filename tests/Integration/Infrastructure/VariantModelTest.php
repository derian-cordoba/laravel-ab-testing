<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Infrastructure;

use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use ABTests\Tests\Integration\DatabaseTestCase;

/**
 * Helper: upsert variant rows for an experiment, mimicking what
 * StartExperimentCommandHandler does — keyed by (experiment_id, key).
 *
 * @param  list<array{key: string, weight: int, is_control: bool}>  $variantData
 */
function syncVariants(ExperimentModel $model, array $variantData): void
{
    foreach ($variantData as $data) {
        VariantModel::query()->updateOrCreate(
            ['experiment_id' => $model->id, 'key' => $data['key']],
            ['weight' => $data['weight'], 'is_control' => $data['is_control']],
        );
    }
}

/**
 * Verifies that the ab_testing_variants table exists, the VariantModel Eloquent
 * class maps to it correctly, and the ExperimentModel::variants() relationship
 * and syncVariants() helper work end-to-end.
 */
final class VariantModelTest extends DatabaseTestCase
{
    public function test_variant_can_be_created_and_retrieved(): void
    {
        $experiment = ExperimentModel::query()->create([
            'key' => 'test-experiment',
            'status' => 'draft',
        ]);

        VariantModel::query()->create([
            'experiment_id' => $experiment->id,
            'key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);

        VariantModel::query()->create([
            'experiment_id' => $experiment->id,
            'key' => 'treatment',
            'weight' => 50,
            'is_control' => false,
        ]);

        $variants = VariantModel::query()
            ->where('experiment_id', $experiment->id)
            ->orderBy('key')
            ->get();

        self::assertCount(2, $variants);

        $control = $variants->firstWhere('key', 'control');
        self::assertNotNull($control);
        self::assertSame(50, $control->weight);
        self::assertTrue($control->is_control);

        $treatment = $variants->firstWhere('key', 'treatment');
        self::assertNotNull($treatment);
        self::assertSame(50, $treatment->weight);
        self::assertFalse($treatment->is_control);
    }

    public function test_experiment_has_many_variants_relationship(): void
    {
        $experiment = ExperimentModel::query()->create([
            'key' => 'another-experiment',
            'status' => 'draft',
        ]);

        VariantModel::query()->create([
            'experiment_id' => $experiment->id,
            'key' => 'control',
            'weight' => 50,
            'is_control' => true,
        ]);
        VariantModel::query()->create([
            'experiment_id' => $experiment->id,
            'key' => 'treatment',
            'weight' => 50,
            'is_control' => false,
        ]);

        $loaded = ExperimentModel::query()->with('variants')->find($experiment->id);

        self::assertCount(2, $loaded->variants);
        self::assertInstanceOf(VariantModel::class, $loaded->variants->first());
    }

    public function test_sync_variants_creates_rows_idempotently(): void
    {
        $experiment = ExperimentModel::query()->create([
            'key' => 'sync-experiment',
            'status' => 'draft',
        ]);

        $variantData = [
            ['key' => 'control',   'weight' => 50, 'is_control' => true],
            ['key' => 'treatment', 'weight' => 50, 'is_control' => false],
        ];

        // First sync — should insert two rows.
        syncVariants($experiment, $variantData);
        self::assertCount(2, $experiment->variants()->get());

        // Second sync with same data — should not create duplicates.
        syncVariants($experiment, $variantData);
        self::assertCount(2, $experiment->variants()->get());
    }

    public function test_sync_variants_updates_weight_on_re_sync(): void
    {
        $experiment = ExperimentModel::query()->create([
            'key' => 'ramp-experiment',
            'status' => 'draft',
        ]);

        syncVariants($experiment, [
            ['key' => 'control',   'weight' => 50, 'is_control' => true],
            ['key' => 'treatment', 'weight' => 50, 'is_control' => false],
        ]);

        // Re-sync with updated weights (e.g. a traffic ramp).
        syncVariants($experiment, [
            ['key' => 'control',   'weight' => 30, 'is_control' => true],
            ['key' => 'treatment', 'weight' => 70, 'is_control' => false],
        ]);

        $treatment = $experiment->variants()->where('key', 'treatment')->first();
        self::assertSame(70, $treatment->weight);

        $control = $experiment->variants()->where('key', 'control')->first();
        self::assertSame(30, $control->weight);
    }

    public function test_deleting_experiment_cascades_to_variants(): void
    {
        $experiment = ExperimentModel::query()->create([
            'key' => 'cascade-experiment',
            'status' => 'draft',
        ]);

        VariantModel::query()->create([
            'experiment_id' => $experiment->id,
            'key' => 'control',
            'weight' => 100,
            'is_control' => true,
        ]);

        $experimentId = $experiment->id;
        $experiment->delete();

        $remaining = VariantModel::query()->where('experiment_id', $experimentId)->count();
        self::assertSame(0, $remaining);
    }

    public function test_variant_belongs_to_experiment(): void
    {
        $experiment = ExperimentModel::query()->create([
            'key' => 'belongs-to-experiment',
            'status' => 'draft',
        ]);

        $variant = VariantModel::query()->create([
            'experiment_id' => $experiment->id,
            'key' => 'control',
            'weight' => 100,
            'is_control' => true,
        ]);

        $loadedExperiment = $variant->experiment;

        self::assertInstanceOf(ExperimentModel::class, $loadedExperiment);
        self::assertSame('belongs-to-experiment', $loadedExperiment->key);
    }
}
