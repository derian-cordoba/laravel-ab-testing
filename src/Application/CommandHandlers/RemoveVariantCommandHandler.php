<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\RemoveVariantCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\VariantModel;
use Illuminate\Support\Carbon;

final readonly class RemoveVariantCommandHandler
{
    public function handle(RemoveVariantCommand $command): void
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

        if ($variant->is_control) {
            throw new InvalidVariantOperation(
                'Cannot remove the control variant. Designate another variant as control first.'
            );
        }

        $totalVariants = VariantModel::query()
            ->where('experiment_id', $model->id)
            ->count();

        if ($totalVariants < 3) {
            throw new InvalidVariantOperation(
                'An experiment must have at least 2 variants (one control and one treatment). Cannot remove this variant.'
            );
        }

        $before = ['key' => $variant->key, 'weight' => $variant->weight];

        $variant->delete();

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'remove_variant',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => $before,
            'after_state'      => null,
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
