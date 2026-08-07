<?php

declare(strict_types=1);

namespace ABTests\Governance\Database;

use ABTests\Enums\ApprovalStatus;
use ABTests\Governance\Contracts\ApprovalPolicy;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;

/**
 * Eloquent adapter for ApprovalPolicy.
 *
 * Queries the ab_testing_experiment_approvals table to determine whether a
 * given experiment has received an approved review. This is the only place
 * in the non-governance layer that is allowed to know about the approval model.
 */
final readonly class DatabaseApprovalPolicy implements ApprovalPolicy
{
    public function hasApprovedReview(string $experimentKey): bool
    {
        return ExperimentApprovalModel::query()
            ->where('experiment_key', $experimentKey)
            ->where('status', ApprovalStatus::approved->value)
            ->exists();
    }
}
