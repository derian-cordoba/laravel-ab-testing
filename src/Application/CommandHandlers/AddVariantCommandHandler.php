<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\AddVariantCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Support\Carbon;

final readonly class AddVariantCommandHandler
{
    public function handle(AddVariantCommand $command): void
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

        if ($command->weight < 1 || $command->weight > 99) {
            throw new InvalidVariantOperation('Variant weight must be between 1 and 99.');
        }

        $existing = VariantModel::query()
            ->where('experiment_id', $model->id)
            ->get();

        $keyExists = $existing->contains('key', $command->variantKey);

        if ($keyExists) {
            throw new InvalidVariantOperation(
                "A variant with key [{$command->variantKey}] already exists in this experiment."
            );
        }

        if ($command->isControl && $existing->contains('is_control', true)) {
            throw new InvalidVariantOperation(
                'Another control variant already exists. Mark the new variant as treatment, or update the existing control first.'
            );
        }

        $newTotal = $existing->sum('weight') + $command->weight;

        if ($newTotal > 100) {
            throw new InvalidVariantOperation(
                "Adding this variant would bring the total allocation to {$newTotal}%. Reduce other variant weights first."
            );
        }

        VariantModel::query()->create([
            'experiment_id' => $model->id,
            'key'           => $command->variantKey,
            'weight'        => $command->weight,
            'is_control'    => $command->isControl,
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'add_variant',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => null,
            'after_state'      => [
                'variant_key' => $command->variantKey,
                'weight'      => $command->weight,
                'is_control'  => $command->isControl,
            ],
            'occurred_at' => Carbon::now(),
        ]);
    }
}
