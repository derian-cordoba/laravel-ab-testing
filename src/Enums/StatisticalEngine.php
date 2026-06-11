<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Which analysis approach to run for an experiment.
 */
enum StatisticalEngine: string
{
    case frequentist = 'frequentist'; // p-value, confidence interval
    case bayesian = 'bayesian';       // probability to beat control, expected loss
    case both = 'both';               // run and display both side by side
}
