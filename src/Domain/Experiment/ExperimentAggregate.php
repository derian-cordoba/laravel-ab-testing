<?php

declare(strict_types=1);

namespace ABTests\Domain\Experiment;

use ABTests\Domain\Events\ExperimentEnvironmentsUpdatedEvent;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Domain\Events\ExperimentStartedEvent;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Values\ExperimentRecord;
use DateTimeImmutable;

/**
 * Aggregate root for the Experiment bounded context.
 *
 * Owns all lifecycle invariants (valid state transitions, traffic clamping, etc.)
 * and raises domain events. The application layer reconstitutes the aggregate from
 * an ExperimentRecord, calls the appropriate domain method, then persists the
 * pending changes and publishes the collected events.
 *
 * Usage in a command handler:
 *
 *   $record    = $this->experimentRepository->getByKey($command->experimentKey);
 *   $aggregate = ExperimentAggregate::reconstitute($record);
 *   $aggregate->pause($command->actorIdentifier, $command->actorType);
 *
 *   $this->experimentRepository->update($key, $aggregate->pendingChanges());
 *   $this->auditLogRepository->append(..., before: $aggregate->beforeState(), after: $aggregate->pendingChanges());
 *   $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
 */
final class ExperimentAggregate
{
    private ExperimentStatus $status;

    private int $trafficPercentage;

    private bool $isKilled;

    private ?DateTimeImmutable $startedAt;

    /** @var array<string, mixed> */
    private array $before = [];

    /** @var array<string, mixed> */
    private array $changes = [];

    /** @var list<object> */
    private array $events = [];

    private function __construct(private readonly ExperimentRecord $record)
    {
        $this->status = ExperimentStatus::from($record->status);
        $this->trafficPercentage = $record->trafficPercentage;
        $this->isKilled = $record->isKilled;
        $this->startedAt = $record->startedAt;
    }

    public static function reconstitute(ExperimentRecord $record): self
    {
        return new self($record);
    }

    // ------------------------------------------------------------------
    // Lifecycle transitions
    // ------------------------------------------------------------------

    public function start(string $actorIdentifier, string $actorType): void
    {
        if (! $this->status->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($this->status, ExperimentStatus::running);
        }

        $traffic = $this->trafficPercentage > 0 ? $this->trafficPercentage : 100;
        $startedAt = $this->startedAt ?? new DateTimeImmutable();

        $this->before = ['status' => $this->status->value, 'started_at' => $this->startedAt];

        $this->apply([
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => $traffic,
            'started_at' => $startedAt,
        ]);

        $this->status = ExperimentStatus::running;
        $this->trafficPercentage = $traffic;
        $this->startedAt = $startedAt;

        $this->events[] = new ExperimentStartedEvent(
            experimentKey: $this->record->key,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
            trafficPercentage: $traffic,
        );
    }

    public function pause(string $actorIdentifier, string $actorType): void
    {
        if (! $this->status->canTransitionTo(ExperimentStatus::paused)) {
            throw new InvalidStateTransition($this->status, ExperimentStatus::paused);
        }

        $this->before = ['status' => $this->status->value];

        $this->apply(['status' => ExperimentStatus::paused->value]);

        $this->status = ExperimentStatus::paused;

        $this->events[] = new ExperimentPausedEvent(
            experimentKey: $this->record->key,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function stop(string $actorIdentifier, string $actorType): void
    {
        if (! $this->status->canTransitionTo(ExperimentStatus::completed)) {
            throw new InvalidStateTransition($this->status, ExperimentStatus::completed);
        }

        $this->before = ['status' => $this->status->value];

        $this->apply([
            'status' => ExperimentStatus::completed->value,
            'stopped_at' => new DateTimeImmutable(),
        ]);

        $this->status = ExperimentStatus::completed;

        $this->events[] = new ExperimentStoppedEvent(
            experimentKey: $this->record->key,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function resume(string $actorIdentifier, string $actorType): void
    {
        if (! $this->status->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($this->status, ExperimentStatus::running);
        }

        $this->before = ['status' => $this->status->value];

        $this->apply(['status' => ExperimentStatus::running->value]);

        $this->status = ExperimentStatus::running;

        $this->events[] = new ExperimentResumedEvent(
            experimentKey: $this->record->key,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function archive(string $actorIdentifier, string $actorType): void
    {
        if (! $this->status->canTransitionTo(ExperimentStatus::archived)) {
            throw new InvalidStateTransition($this->status, ExperimentStatus::archived);
        }

        $this->before = ['status' => $this->status->value];

        $this->apply(['status' => ExperimentStatus::archived->value]);

        $this->status = ExperimentStatus::archived;
    }

    public function rampTraffic(int $percentage, string $actorIdentifier, string $actorType): void
    {
        $clamped = max(0, min(100, $percentage));

        $this->before = ['traffic_percentage' => $this->trafficPercentage];

        $this->apply(['traffic_percentage' => $clamped]);

        $this->trafficPercentage = $clamped;
    }

    public function activateKillSwitch(bool $kill, string $actorIdentifier, string $actorType): void
    {
        $this->before = ['is_killed' => $this->isKilled];

        $this->apply([
            'is_killed' => $kill,
            'killed_at' => $kill ? new DateTimeImmutable() : null,
        ]);

        $this->isKilled = $kill;

        $this->events[] = new KillSwitchActivatedEvent(
            experimentKey: $this->record->key,
            flagKey: null,
            activated: $kill,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    /**
     * @param  list<string>|null  $allowedEnvironments  null = all environments.
     */
    public function setEnvironments(?array $allowedEnvironments, string $actorIdentifier, string $actorType): void
    {
        $this->before = ['allowed_environments' => $this->record->allowedEnvironments];

        $this->apply(['allowed_environments' => $allowedEnvironments]);

        $this->events[] = new ExperimentEnvironmentsUpdatedEvent(
            experimentKey: $this->record->key,
            allowedEnvironments: $allowedEnvironments,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    // ------------------------------------------------------------------
    // Accessors for the application layer
    // ------------------------------------------------------------------

    public function key(): string
    {
        return $this->record->key;
    }

    public function trafficPercentage(): int
    {
        return $this->trafficPercentage;
    }

    /** @return array<string, mixed> */
    public function beforeState(): array
    {
        return $this->before;
    }

    /** @return array<string, mixed> */
    public function pendingChanges(): array
    {
        return $this->changes;
    }

    /**
     * Return and clear collected domain events so the application layer can
     * publish them after persisting the state change.
     *
     * @return list<object>
     */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $changes */
    private function apply(array $changes): void
    {
        $this->changes = [...$this->changes, ...$changes];
    }
}
