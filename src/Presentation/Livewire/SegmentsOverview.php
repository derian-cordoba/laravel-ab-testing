<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Values\Criterion;
use ABTests\Values\Segment;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Displays the audience segment criteria declared on every registered
 * experiment definition. Experiments with Segment::any() (no criteria) are
 * shown as "All traffic". Useful for auditing targeting logic across the fleet.
 */
final class SegmentsOverview extends Component
{
    public function render(): View
    {
        $registry = app(ExperimentRegistry::class);
        $definitions = $registry->all();

        $dbExperiments = ExperimentModel::query()
            ->select(['key', 'name', 'status'])
            ->get()
            ->keyBy('key')
            ->all();

        $rows = [];

        foreach ($definitions as $experimentKey => $definition) {
            $displayName = $definition->name
                ?? $dbExperiments[$experimentKey]?->name
                ?? $experimentKey;

            $status = $dbExperiments[$experimentKey]?->status ?? null;
            $criteria = $definition->audience->criteria;

            $rows[] = [
                'key' => $experimentKey,
                'name' => $displayName,
                'status' => $status,
                'criteria' => array_map(
                    static fn (Criterion $c): array => [
                        'attribute' => $c->attribute,
                        'operator' => $c->operator->value,
                        'expected' => is_array($c->expected)
                            ? implode(', ', $c->expected)
                            : (string) $c->expected,
                    ],
                    $criteria,
                ),
                'isOpenAudience' => $criteria === [],
            ];
        }

        // Sort: experiments with criteria first, then open audience; alpha within each.
        usort($rows, static function (array $a, array $b): int {
            if ($a['isOpenAudience'] !== $b['isOpenAudience']) {
                return $a['isOpenAudience'] <=> $b['isOpenAudience'];
            }

            return strcmp($a['key'], $b['key']);
        });

        return view('ab-testing::livewire.segments-overview', compact('rows'))
            ->layout('ab-testing::layout', ['title' => 'A/B Testing — Segments']);
    }
}
