<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Infrastructure\Database\Models\AuditLogModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

use function is_array;

/**
 * Full-page component for the system-wide audit log. Shows all recorded
 * privileged actions across every experiment, filterable by experiment key,
 * action type, and actor. Uses load-more pagination to avoid relying on the
 * host app's paginator views.
 */
final class AuditLog extends Component
{
    #[Url(as: 'experiment', except: '')]
    public string $experimentFilter = '';

    #[Url(as: 'action', except: '')]
    public string $actionFilter = '';

    #[Url(as: 'actor', except: '')]
    public string $actorFilter = '';

    public int $perPage = 50;

    public function loadMore(): void
    {
        $this->perPage += 50;
    }

    public function render(): View
    {
        $query = AuditLogModel::query()->orderByDesc('occurred_at');

        if ($this->experimentFilter !== '') {
            $query->where('experiment_key', 'like', '%'.$this->experimentFilter.'%');
        }

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }

        if ($this->actorFilter !== '') {
            $query->where('actor_identifier', 'like', '%'.$this->actorFilter.'%');
        }

        $total = $query->count();
        $entries = $query->limit($this->perPage)->get()->map(function (AuditLogModel $entry): AuditLogModel {
            $entry->before_state_display = $this->formatState($entry->before_state);
            $entry->after_state_display = $this->formatState($entry->after_state);

            return $entry;
        });
        $hasMore = $total > $this->perPage;

        $distinctActions = AuditLogModel::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('ab-testing::livewire.audit-log', compact('entries', 'total', 'hasMore', 'distinctActions'))
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — Audit Log']);
    }

    /** @param array<string, mixed>|null $state */
    private function formatState(?array $state): string
    {
        if ($state === null || $state === []) {
            return '—';
        }

        return collect($state)
            ->map(fn (mixed $value, string $key): string => "$key: ".(is_array($value)
                    ? json_encode($value)
                    : (string) $value),
            )
            ->implode(', ');
    }
}
