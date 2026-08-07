<?php

declare(strict_types=1);

namespace ABTests\Enums;

use function in_array;

/**
 * The operational lifecycle of an experiment. This state lives in the
 * database (not in code) and is driven from the dashboard. The transition
 * table is the single source of truth for what the controls may do.
 */
enum ExperimentStatus: string
{
    case draft = 'draft';
    case scheduled = 'scheduled';
    case running = 'running';
    case paused = 'paused';
    case completed = 'completed';
    case archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::draft => [self::scheduled, self::running, self::archived],
            self::scheduled => [self::running, self::draft, self::archived],
            self::running => [self::paused, self::completed],
            self::paused => [self::running, self::completed],
            self::completed => [self::archived],
            self::archived => [],
        };
    }

    public function isLive(): bool
    {
        return $this === self::running;
    }

    public function label(): string
    {
        return match ($this) {
            self::draft => 'Draft',
            self::scheduled => 'Scheduled',
            self::running => 'Running',
            self::paused => 'Paused',
            self::completed => 'Completed',
            self::archived => 'Archived',
        };
    }

}
