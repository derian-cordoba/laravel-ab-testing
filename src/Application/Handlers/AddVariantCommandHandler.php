<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\AddVariantCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidVariantOperation;
use ABTests\Infrastructure\Database\Models\VariantModel;

final readonly class AddVariantCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(AddVariantCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        $status = ExperimentStatus::from($record->status);

        if (in_array($status, [ExperimentStatus::completed, ExperimentStatus::archived], true)) {
            throw new InvalidVariantOperation(
                "Variants cannot be changed on completed or archived experiments. Current status: [{$status->value}].",
            );
        }

        if ($command->weight < 1 || $command->weight > 99) {
            throw new InvalidVariantOperation('Variant weight must be between 1 and 99.');
        }

        $existing = VariantModel::query()
            ->where('experiment_id', $record->id)
            ->get();

        $keyExists = $existing->contains('key', $command->variantKey);

        if ($keyExists) {
            throw new InvalidVariantOperation(
                "A variant with key [{$command->variantKey}] already exists in this experiment.",
            );
        }

        if ($command->isControl && $existing->contains('is_control', true)) {
            throw new InvalidVariantOperation(
                'Another control variant already exists. Mark the new variant as treatment, or update the existing control first.',
            );
        }

        $newTotal = $existing->sum('weight') + $command->weight;

        if ($newTotal > 100) {
            throw new InvalidVariantOperation(
                "Adding this variant would bring the total allocation to {$newTotal}%. Reduce other variant weights first.",
            );
        }

        VariantModel::query()->create([
            'experiment_id' => $record->id,
            'key' => $command->variantKey,
            'weight' => $command->weight,
            'is_control' => $command->isControl,
        ]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'add_variant',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: [],
            after: [
                'variant_key' => $command->variantKey,
                'weight' => $command->weight,
                'is_control' => $command->isControl,
            ],
        );
    }
}
