<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

final class ApprovalRequired extends \DomainException implements ABTestingException
{
    public function __construct(string $experimentKey)
    {
        parent::__construct(
            "Experiment [$experimentKey] requires an approved review before it can be started. " .
            'Submit an approval request from the dashboard and have an admin approve it first.'
        );
    }
}
