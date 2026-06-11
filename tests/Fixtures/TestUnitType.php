<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsUnit;
use ABTests\Contracts\Bucketable;

#[AsUnit(key: 'test-user')]
final class TestUnitType implements Bucketable
{
    /** @param array<string, mixed> $attributes */
    public function __construct(
        private string $id = 'user-1',
        private array $attributes = [],
    ) {
    }

    public function bucketingKey(): string
    {
        return "user:{$this->id}";
    }

    public function attributes(): array
    {
        return $this->attributes;
    }
}
