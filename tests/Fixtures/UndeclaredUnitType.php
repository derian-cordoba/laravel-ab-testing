<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Contracts\Bucketable;

final class UndeclaredUnitType implements Bucketable
{
    public function bucketingKey(): string
    {
        return 'user:missing-attribute';
    }

    public function attributes(): array
    {
        return [];
    }
}
