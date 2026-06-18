<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Contracts\Bucketable;

final class TestUnit implements Bucketable
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        private string $id = 'unit-1',
        private array $attributes = [],
    ) {}

    public function bucketingKey(): string
    {
        return $this->id;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }
}
