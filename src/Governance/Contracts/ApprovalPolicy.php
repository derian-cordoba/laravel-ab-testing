<?php

declare(strict_types=1);

namespace ABTests\Governance\Contracts;

/**
 * Port for querying the experiment approval state.
 *
 * Keeps the StartExperimentCommandHandler decoupled from the Governance
 * infrastructure (ExperimentApprovalModel) by providing a narrow, domain-
 * language interface that only exposes the decision the handler needs.
 */
interface ApprovalPolicy
{
    /**
     * Return true when the experiment has at least one approved review.
     */
    public function hasApprovedReview(string $experimentKey): bool;
}
