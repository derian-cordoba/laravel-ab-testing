<?php

declare(strict_types=1);

namespace ABTests\Presentation\Support;

use ABTests\Enums\ExperimentStatus;

final class ExperimentStatusPresenter
{
    public static function badgeClass(ExperimentStatus $status): string
    {
        return match ($status) {
            ExperimentStatus::draft     => 'bg-gray-700 text-gray-300',
            ExperimentStatus::scheduled => 'bg-blue-900/60 text-blue-300',
            ExperimentStatus::running   => 'bg-green-900/60 text-green-300',
            ExperimentStatus::paused    => 'bg-yellow-900/60 text-yellow-300',
            ExperimentStatus::completed => 'bg-violet-900/60 text-violet-300',
            ExperimentStatus::archived  => 'bg-gray-800 text-gray-500',
        };
    }

    public static function dotClass(ExperimentStatus $status): string
    {
        return match ($status) {
            ExperimentStatus::draft     => 'bg-gray-500',
            ExperimentStatus::scheduled => 'bg-blue-400',
            ExperimentStatus::running   => 'bg-green-400',
            ExperimentStatus::paused    => 'bg-yellow-400',
            ExperimentStatus::completed => 'bg-violet-400',
            ExperimentStatus::archived  => 'bg-gray-600',
        };
    }
}
