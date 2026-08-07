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

}
