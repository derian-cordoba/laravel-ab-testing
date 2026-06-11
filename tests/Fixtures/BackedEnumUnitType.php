<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsUnit;
use ABTests\Contracts\Bucketable;

#[AsUnit(key: TenantUnitKey::tenant)]
final class BackedEnumUnitType implements Bucketable
{
    public function bucketingKey(): string
    {
        return 'tenant:acme';
    }

    public function attributes(): array
    {
        return [];
    }
}
