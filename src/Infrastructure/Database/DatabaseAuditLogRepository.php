<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\AuditLogRepository;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use Illuminate\Support\Carbon;

final readonly class DatabaseAuditLogRepository implements AuditLogRepository
{
    public function append(string $experimentKey, string $action, string $actorIdentifier, string $actorType, array $before = [], array $after = []): void
    {
        AuditLogModel::query()->create([
            'actor_identifier' => $actorIdentifier,
            'actor_type'       => $actorType,
            'action'           => $action,
            'experiment_key'   => $experimentKey,
            'before_state'     => $before ?: null,
            'after_state'      => $after ?: null,
            'occurred_at'      => Carbon::now(),
        ]);
    }

    public function appendForFlag(string $flagKey, string $action, string $actorIdentifier, string $actorType, array $before = [], array $after = []): void
    {
        AuditLogModel::query()->create([
            'actor_identifier' => $actorIdentifier,
            'actor_type'       => $actorType,
            'action'           => $action,
            'experiment_key'   => null,
            'before_state'     => array_merge(['flag_key' => $flagKey], $before) ?: null,
            'after_state'      => array_merge(['flag_key' => $flagKey], $after) ?: null,
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
