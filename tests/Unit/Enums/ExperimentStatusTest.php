<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Enums;

use ABTests\Enums\ExperimentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExperimentStatusTest extends TestCase
{
    #[Test]
    public function only_running_is_live(): void
    {
        self::assertTrue(ExperimentStatus::running->isLive());

        foreach ([
            ExperimentStatus::draft,
            ExperimentStatus::scheduled,
            ExperimentStatus::paused,
            ExperimentStatus::completed,
            ExperimentStatus::archived,
        ] as $status) {
            self::assertFalse($status->isLive(), "{$status->value} should not be live");
        }
    }

    /** @return array<string, array{ExperimentStatus, ExperimentStatus, bool}> */
    public static function transitionProvider(): array
    {
        return [
            'draft → scheduled' => [ExperimentStatus::draft,     ExperimentStatus::scheduled,  true],
            'draft → running' => [ExperimentStatus::draft,     ExperimentStatus::running,    true],
            'draft → archived' => [ExperimentStatus::draft,     ExperimentStatus::archived,   true],
            'draft → paused (invalid)' => [ExperimentStatus::draft,     ExperimentStatus::paused,     false],
            'scheduled → running' => [ExperimentStatus::scheduled, ExperimentStatus::running,    true],
            'scheduled → draft' => [ExperimentStatus::scheduled, ExperimentStatus::draft,      true],
            'scheduled → archived' => [ExperimentStatus::scheduled, ExperimentStatus::archived,   true],
            'running → paused' => [ExperimentStatus::running,   ExperimentStatus::paused,     true],
            'running → completed' => [ExperimentStatus::running,   ExperimentStatus::completed,  true],
            'running → draft (invalid)' => [ExperimentStatus::running,   ExperimentStatus::draft,      false],
            'paused → running' => [ExperimentStatus::paused,    ExperimentStatus::running,    true],
            'paused → completed' => [ExperimentStatus::paused,    ExperimentStatus::completed,  true],
            'completed → archived' => [ExperimentStatus::completed, ExperimentStatus::archived,   true],
            'completed → running' => [ExperimentStatus::completed, ExperimentStatus::running,    false],
            'archived → anything' => [ExperimentStatus::archived,  ExperimentStatus::draft,      false],
        ];
    }

    #[Test]
    #[DataProvider('transitionProvider')]
    public function state_machine_transitions(
        ExperimentStatus $from,
        ExperimentStatus $to,
        bool $allowed,
    ): void {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }
}
