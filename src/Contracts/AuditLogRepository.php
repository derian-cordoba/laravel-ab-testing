<?php

declare(strict_types=1);

namespace ABTests\Contracts;

interface AuditLogRepository
{
    public function append(string $experimentKey, string $action, string $actorIdentifier, string $actorType, array $before = [], array $after = []): void;

    public function appendForFlag(string $flagKey, string $action, string $actorIdentifier, string $actorType, array $before = [], array $after = []): void;
}
