<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\UpdateVariantCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\VariantModel;

final readonly class UpdateVariantCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(UpdateVariantCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

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

        if ($variant->is_control && ! $command->isControl) {
            throw new InvalidVariantOperation(
                'Cannot remove control designation from this variant. Set another variant as control first.'
            );
        }

        $before = ['key' => $variant->key, 'weight' => $variant->weight, 'is_control' => $variant->is_control];

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

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'update_variant',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $before,
            after: [
                'variant_key' => $command->variantKey,
                'weight'      => $command->weight,
                'is_control'  => $command->isControl,
            ],
        );
    }
}
