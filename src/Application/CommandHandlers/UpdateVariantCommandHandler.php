<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\UpdateVariantCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Support\Carbon;

final readonly class UpdateVariantCommandHandler
{
    public function handle(UpdateVariantCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $status = ExperimentStatus::from($model->status);

        if (in_array($status, [ExperimentStatus::completed, ExperimentStatus::archived], true)) {
            throw new InvalidVariantOperation(
                "Variants cannot be changed on completed or archived experiments. Current status: [{$status->value}]."
            );
        }

        /** @var VariantModel|null $variant */
        $variant = VariantModel::query()
            ->where('experiment_id', $model->id)
            ->where('id', $command->variantId)
            ->first();

        if ($variant === null) {
            throw new InvalidVariantOperation('Variant not found in this experiment.');
        }

        if ($command->weight < 1 || $command->weight > 99) {
            throw new InvalidVariantOperation('Variant weight must be between 1 and 99.');
        }

        // Key uniqueness — exclude the variant being edited.
        $keyConflict = VariantModel::query()
            ->where('experiment_id', $model->id)
            ->where('key', $command->variantKey)
            ->where('id', '!=', $command->variantId)
            ->exists();

        if ($keyConflict) {
            throw new InvalidVariantOperation(
                "A variant with key [{$command->variantKey}] already exists in this experiment."
            );
        }

        // Cannot un-set control without designating another as control.
        if ($variant->is_control && ! $command->isControl) {
            throw new InvalidVariantOperation(
                'Cannot remove control designation from this variant. Set another variant as control first.'
            );
        }

        $before = ['key' => $variant->key, 'weight' => $variant->weight, 'is_control' => $variant->is_control];

        // If this variant is being promoted to control, demote the existing one.
        if ($command->isControl && ! $variant->is_control) {
            VariantModel::query()
                ->where('experiment_id', $model->id)
                ->where('is_control', true)
                ->update(['is_control' => false]);
        }

        $variant->update([
            'key'        => $command->variantKey,
            'weight'     => $command->weight,
            'is_control' => $command->isControl,
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'update_variant',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => $before,
            'after_state'      => [
                'variant_key' => $command->variantKey,
                'weight'      => $command->weight,
                'is_control'  => $command->isControl,
            ],
            'occurred_at' => Carbon::now(),
        ]);
    }
}
