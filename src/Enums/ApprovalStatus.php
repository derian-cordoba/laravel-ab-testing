<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The lifecycle state of an experiment approval request.
 */
enum ApprovalStatus: string
{
    case pending = 'pending';
    case approved = 'approved';
    case rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::pending => 'Pending Review',
            self::approved => 'Approved',
            self::rejected => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::pending => 'bg-yellow-100 text-yellow-800',
            self::approved => 'bg-green-100 text-green-800',
            self::rejected => 'bg-red-100 text-red-800',
        };
    }
}
