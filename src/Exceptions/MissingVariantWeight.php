<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use LogicException;

final class MissingVariantWeight extends LogicException implements ABTestingException
{
    public function __construct(string $enum, string $case)
    {
        parent::__construct(
            "Variant $enum::$case is missing a #[Weight(...)] attribute."
        );
    }
}
