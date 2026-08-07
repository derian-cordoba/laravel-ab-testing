<?php

declare(strict_types=1);

namespace ABTests\Presentation\Support;

use ABTests\Enums\ApprovalStatus;

final class ApprovalStatusPresenter
{
    public static function badgeClass(ApprovalStatus $status): string
    {
        return match ($status) {
            ApprovalStatus::pending  => 'bg-yellow-100 text-yellow-800',
            ApprovalStatus::approved => 'bg-green-100 text-green-800',
            ApprovalStatus::rejected => 'bg-red-100 text-red-800',
        };
    }
}
