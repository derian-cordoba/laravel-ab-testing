<?php

declare(strict_types=1);

namespace ABTests\Domain\FeatureFlag;

use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use ABTests\Domain\Events\FeatureFlagEnvironmentsUpdatedEvent;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Enums\ConditionsLogic;
use ABTests\Values\FeatureFlagRecord;

use function array_key_exists;

/**
 * Aggregate root for the FeatureFlag bounded context.
 *
 * Owns all state-transition invariants and raises domain events.
 * The Application layer reconstitutes the aggregate from a FeatureFlagRecord,
 * calls the appropriate domain method, then persists the pending changes and
 * publishes the collected events via dispatchAll().
 *
 * Usage in a command handler:
 *
 *   $record    = $this->featureFlagRepository->getByKey($command->flagKey);
 *   $aggregate = FeatureFlagAggregate::reconstitute($record);
 *   $aggregate->enable($command->actorIdentifier, $command->actorType);
 *
 *   $this->featureFlagRepository->update($key, $aggregate->pendingChanges());
 *   $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
 */
final class FeatureFlagAggregate
{
    private bool $isEnabled;

    private bool $isKilled;

    private int $rolloutPercentage;

    /** @var list<array{attribute:string,operator:string,expected:mixed}>|null */
    private ?array $conditions;

    private ConditionsLogic $conditionsLogic;

    /** @var list<string>|null */
    private ?array $allowedEnvironments;

    /** @var array<string, mixed> */
    private array $before = [];

    /** @var array<string, mixed> */
    private array $changes = [];

    /** @var list<object> */
    private array $events = [];

    private function __construct(private readonly FeatureFlagRecord $record)
    {
        $this->isEnabled = $record->isEnabled;
        $this->isKilled = $record->isKilled;
        $this->rolloutPercentage = $record->rolloutPercentage;
        $this->conditions = $record->conditions;
        $this->conditionsLogic = $record->conditionsLogic;
        $this->allowedEnvironments = $record->allowedEnvironments;
    }

    public static function reconstitute(FeatureFlagRecord $record): self
    {
        return new self($record);
    }

    // ------------------------------------------------------------------
    // Lifecycle mutations
    // ------------------------------------------------------------------

    public function enable(string $actorIdentifier, string $actorType): void
    {
        $this->capture('is_enabled', $this->isEnabled, true);
        $this->isEnabled = true;

        $this->events[] = new FeatureFlagEnabledEvent(
            flagKey: $this->record->key,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function disable(string $actorIdentifier, string $actorType): void
    {
        $this->capture('is_enabled', $this->isEnabled, false);
        $this->isEnabled = false;

        $this->events[] = new FeatureFlagDisabledEvent(
            flagKey: $this->record->key,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function activateKillSwitch(string $actorIdentifier, string $actorType): void
    {
        $this->capture('killed_at', $this->isKilled ? 'now' : null, 'now');
        $this->changes['killed_at'] = now();
        $this->isKilled = true;

        $this->events[] = new KillSwitchActivatedEvent(
            experimentKey: null,
            flagKey: $this->record->key,
            activated: true,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function deactivateKillSwitch(string $actorIdentifier, string $actorType): void
    {
        $this->capture('killed_at', $this->isKilled ? 'now' : null, null);
        $this->changes['killed_at'] = null;
        $this->isKilled = false;

        $this->events[] = new KillSwitchActivatedEvent(
            experimentKey: null,
            flagKey: $this->record->key,
            activated: false,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    public function setRolloutPercentage(int $percentage): void
    {
        $percentage = max(0, min(100, $percentage));

        $this->capture('rollout_percentage', $this->rolloutPercentage, $percentage);
        $this->rolloutPercentage = $percentage;
    }

    /**
     * @param  list<array{attribute:string,operator:string,expected:mixed}>|null  $conditions
     */
    public function setConditions(?array $conditions, ConditionsLogic $logic): void
    {
        $this->capture('conditions', $this->conditions, $conditions);
        $this->capture('conditions_logic', $this->conditionsLogic->value, $logic->value);
        $this->conditions = $conditions;
        $this->conditionsLogic = $logic;
    }

    /**
     * @param  list<string>|null  $allowedEnvironments
     */
    public function setEnvironments(?array $allowedEnvironments, string $actorIdentifier, string $actorType): void
    {
        $this->capture('allowed_environments', $this->allowedEnvironments, $allowedEnvironments);
        $this->allowedEnvironments = $allowedEnvironments;

        $this->events[] = new FeatureFlagEnvironmentsUpdatedEvent(
            flagKey: $this->record->key,
            allowedEnvironments: $allowedEnvironments,
            actorIdentifier: $actorIdentifier,
            actorType: $actorType,
        );
    }

    // ------------------------------------------------------------------
    // Handler protocol
    // ------------------------------------------------------------------

    /**
     * The DB column → value map to pass to FeatureFlagRepository::update().
     *
     * @return array<string, mixed>
     */
    public function pendingChanges(): array
    {
        return $this->changes;
    }

    /**
     * The state before any mutation, for audit logging.
     *
     * @return array<string, mixed>
     */
    public function beforeState(): array
    {
        return $this->before;
    }

    /**
     * Drain and return all collected domain events.
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

    private function capture(string $field, mixed $before, mixed $after): void
    {
        if (! array_key_exists($field, $this->before)) {
            $this->before[$field] = $before;
        }

        $this->changes[$field] = $after;
    }
}
