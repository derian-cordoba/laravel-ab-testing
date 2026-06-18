<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use ABTests\Enums\ExperimentStatus;
use DomainException;

final class InvalidStateTransition extends DomainException implements ABTestingException
{
    public function __construct(ExperimentStatus $from, ExperimentStatus $to)
    {
        parent::__construct(
            "Cannot transition experiment from [$from->value] to [$to->value].",
        );
    }
}
