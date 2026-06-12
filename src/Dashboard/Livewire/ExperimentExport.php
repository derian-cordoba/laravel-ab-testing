<?php

declare(strict_types=1);

namespace ABTests\Dashboard\Livewire;

use ABTests\Application\Services\ExportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Embedded Livewire component that provides CSV and JSON download actions for
 * a single experiment. Triggered from the experiment detail page.
 */
final class ExperimentExport extends Component
{
    public string $experimentKey = '';

    public string $flashMessage = '';
    public string $flashType = 'success';

    public function mount(string $experimentKey): void
    {
        $this->experimentKey = $experimentKey;
    }

    #[On('experiment-updated')]
    public function refresh(): void
    {
        //
    }

    /**
     * Stream the raw events for this experiment as a CSV download.
     */
    public function downloadCsv(): StreamedResponse
    {
        $key = $this->experimentKey;

        return response()->streamDownload(static function () use ($key): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            try {
                app(ExportService::class)->streamEventsCsv($key, $handle);
            } catch (Throwable $e) {
                fputcsv($handle, ['error', $e->getMessage()]);
            } finally {
                fclose($handle);
            }
        }, "$key-events.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Download the rollup summary for this experiment as a JSON file.
     */
    public function downloadJson(): StreamedResponse
    {
        $key = $this->experimentKey;

        return response()->streamDownload(static function () use ($key): void {
            try {
                $data = app(ExportService::class)->rollupAsJson($key);
                echo json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                echo json_encode(['error' => $e->getMessage()]);
            }
        }, "$key-rollup.json", ['Content-Type' => 'application/json']);
    }

    public function render(): View
    {
        $eventCount = 0;

        try {
            $eventCount = app(ExportService::class)->eventCount($this->experimentKey);
        } catch (Throwable) {
            //
        }

        return view('ab-testing::livewire.experiment-export', compact('eventCount'));
    }
}
