<?php

declare(strict_types=1);

namespace ABTests\Presentation\Livewire;

use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Attributes\AsMetric;
use ABTests\Definitions\MetricBinding;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use ReflectionClass;
use Throwable;

/**
 * Catalog of every metric referenced across all registered experiment
 * definitions. Shows each metric's technical configuration and which
 * experiments use it (and in which role).
 */
final class MetricsCatalog extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function render(): View
    {
        $registry = app(ExperimentRegistry::class);
        $definitions = $registry->all();

        // Collect all distinct metric keys with their details.
        // $metrics[key] = [ key, type, event, aggregate, window, roles[] ]
        $metrics = [];
        $usages = [];  // key => [ [experimentKey, experimentName, role] ]

        // Load all experiment DB models for display names.
        $dbExperiments = ExperimentModel::query()
            ->select(['key', 'name'])
            ->get()
            ->keyBy('key')
            ->all();

        foreach ($definitions as $experimentKey => $definition) {
            $displayName = $definition->name
                ?? $dbExperiments[$experimentKey]?->name
                ?? $experimentKey;

            foreach ($definition->metrics as $binding) {
                $metricKey = $binding->metric;

                $usages[$metricKey][] = [
                    'experimentKey' => $experimentKey,
                    'experimentName' => $displayName,
                    'role' => $binding->role,
                    'maximumRegression' => $binding->maximumRegression,
                ];

                if (isset($metrics[$metricKey])) {
                    continue;
                }

                // Attempt to resolve full config from #[AsMetric] on a class.
                $metrics[$metricKey] = $this->resolveMetricInfo($metricKey, $binding);
            }
        }

        // Sort alphabetically.
        ksort($metrics);

        // Apply search filter.
        if ($this->search !== '') {
            $term = mb_strtolower($this->search);
            $metrics = array_filter(
                $metrics,
                static function (array $m) use ($term): bool {
                    return str_contains(mb_strtolower($m['key']), $term)
                        || str_contains(mb_strtolower($m['event'] ?? ''), $term)
                        || str_contains(mb_strtolower($m['type'] ?? ''), $term);
                },
            );
        }

        return view(
            'ab-testing::livewire.metrics-catalog',
            ['metrics' => $metrics, 'usages' => $usages],
        )->layout('ab-testing::layout', ['title' => 'A/B Testing — Metrics']);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * @return array{key: string, type: string|null, event: string|null, aggregate: string|null, window: string|null, class: string|null}
     */
    private function resolveMetricInfo(string $metricKeyOrClass, MetricBinding $binding): array
    {
        $info = [
            'key' => $metricKeyOrClass,
            'type' => null,
            'event' => null,
            'aggregate' => null,
            'window' => null,
            'class' => null,
        ];

        // If the metric string is a class name, read the #[AsMetric] attribute.
        if (class_exists($metricKeyOrClass)) {
            try {
                $reflector = new ReflectionClass($metricKeyOrClass);
                $attrs = $reflector->getAttributes(AsMetric::class);

                if ($attrs !== []) {
                    /** @var AsMetric $asMetric */
                    $asMetric = $attrs[0]->newInstance();
                    $info['key'] = $asMetric->key;
                    $info['type'] = $asMetric->type->value;
                    $info['event'] = $asMetric->event;
                    $info['aggregate'] = $asMetric->aggregate->value;
                    $info['window'] = $asMetric->attributionWindow;
                    $info['class'] = $metricKeyOrClass;
                }
            } catch (Throwable) {
                // Best-effort; leave nulls.
            }
        }

        return $info;
    }
}
