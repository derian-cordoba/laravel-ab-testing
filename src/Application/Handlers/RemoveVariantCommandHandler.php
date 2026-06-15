<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\RemoveVariantCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\VariantModel;

final readonly class RemoveVariantCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(RemoveVariantCommand $command): void
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

        if ($variant->is_control) {
            throw new InvalidVariantOperation(
                'Cannot remove the control variant. Designate another variant as control first.'
            );
        }

        if (in_array($status, [ExperimentStatus::running, ExperimentStatus::paused], true)) {
            $totalVariants = VariantModel::query()
                ->where('experiment_id', $model->id)
                ->count();

            if ($totalVariants < 3) {
                throw new InvalidVariantOperation(
                    'A running or paused experiment must retain at least 2 variants. Cannot remove this variant.'
                );
            }
        }

        $before = ['key' => $variant->key, 'weight' => $variant->weight];

        $variant->delete();

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'remove_variant',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $before,
            after: [],
        );
    }
}
