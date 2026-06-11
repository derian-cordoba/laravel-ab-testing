@use(ABTests\Application\Data\ExperimentResultsData)
@use(ABTests\Application\Data\VariantResultData)
@use(ABTests\Enums\MetricRole)

<div>
    @if($results === null)
        <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-8 text-center">
            <p class="text-sm text-gray-400">No data available for this experiment yet.</p>
        </div>
    @else
        @php
            /** @var ExperimentResultsData $results */
            $definition = $results->definition;
        @endphp

        {{-- Active guardrail breaches --}}
        @if($results->activeGuardrailBreaches->isNotEmpty())
            <div class="mb-6 rounded-lg border border-red-700/50 bg-red-900/20 p-4">
                <h3 class="text-sm font-semibold text-red-300 mb-2">Active Guardrail Breaches</h3>
                <ul class="space-y-1">
                    @foreach($results->activeGuardrailBreaches as $breach)
                        <li class="text-sm text-red-400">
                            Metric <strong class="text-red-300">{{ $breach->metric_key }}</strong> — observed
                            {{ number_format($breach->observed_value, 4) }}, threshold
                            {{ number_format($breach->threshold_value, 4) }}
                            on variant <code class="font-mono text-xs text-red-300">{{ $breach->variant_key }}</code>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- SRM warning --}}
        @if($results->sampleRatioMismatch->detected)
            <div class="mb-6 rounded-lg border border-orange-700/50 bg-orange-900/20 p-4">
                <h3 class="text-sm font-semibold text-orange-300">Sample Ratio Mismatch Detected</h3>
                <p class="mt-1 text-sm text-orange-400/80">
                    Chi-square: {{ number_format($results->sampleRatioMismatch->chiSquare, 3) }} —
                    p-value: {{ number_format($results->sampleRatioMismatch->pValue, 4) }}.
                    Results may not be valid. Investigate assignment before drawing conclusions.
                </p>
            </div>
        @endif

        {{-- Primary metric results table --}}
        @if($results->hasResults())
            <div class="mb-8 overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
                <div class="px-6 py-4 border-b border-gray-700/60 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-100">Variant Results</h2>
                    <span class="text-xs text-gray-500">
                        {{ number_format($results->totalAssignedUnits()) }} units assigned
                        &middot; computed {{ $results->computedAt->format('Y-m-d H:i') }} UTC
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700/60">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Variant</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Units</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Conv. Rate</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Lift</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">p-value</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Prob. to beat</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Verdict</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/40">
                            @foreach($results->variantResults as $variantResult)
                                @php
                                    $primary = $variantResult->primaryMetricSummary;
                                    $verdict = $variantResult->verdictResult;
                                    $isControl = $variantResult->variant->isControl();
                                    $lift = $verdict?->frequentist?->relativeLift ?? $verdict?->bayesian?->relativeLift;
                                @endphp
                                <tr class="hover:bg-gray-800/40 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono text-sm text-gray-200">{{ $variantResult->variant->key() }}</span>
                                            @if($isControl)
                                                <span class="inline-flex items-center rounded bg-gray-700 px-1.5 py-0.5 text-xs text-gray-400">control</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-300 tabular-nums">
                                        {{ number_format($primary->countOfUnits) }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-300 tabular-nums">
                                        {{ number_format($primary->conversionRate * 100, 2) }}%
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm tabular-nums">
                                        @if($lift !== null)
                                            <span class="{{ $lift >= 0 ? 'text-green-400' : 'text-red-400' }} font-medium">
                                                {{ $lift >= 0 ? '+' : '' }}{{ number_format($lift * 100, 2) }}%
                                            </span>
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-300 tabular-nums">
                                        @if($verdict?->frequentist !== null)
                                            {{ number_format($verdict->frequentist->pValue ?? 1.0, 4) }}
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-sm text-gray-300 tabular-nums">
                                        @if($verdict?->bayesian !== null)
                                            {{ number_format(($verdict->bayesian->probabilityToBeatControl ?? 0) * 100, 1) }}%
                                        @else
                                            <span class="text-gray-600">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($isControl)
                                            <span class="text-gray-600 text-sm">baseline</span>
                                        @elseif($verdict !== null)
                                            <x-ab-testing::verdict-badge :verdict="$verdict->verdict" />
                                        @else
                                            <span class="text-gray-600 text-sm">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Secondary metrics per variant --}}
            @php
                $secondaryBindings = array_values(array_filter(
                    $definition->metrics,
                    static fn ($b) => $b->role === MetricRole::secondary
                ));
            @endphp
            @foreach($results->variantResults as $variantResult)
                @if(!empty($variantResult->secondaryMetricSummaries))
                    <div class="mb-4 overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
                        <div class="px-6 py-3 border-b border-gray-700/60">
                            <h3 class="text-sm font-semibold text-gray-300">
                                Secondary metrics — <span class="font-mono text-gray-400">{{ $variantResult->variant->key() }}</span>
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-700/40">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Metric</th>
                                        <th class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Units</th>
                                        <th class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Conv. Rate</th>
                                        <th class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Mean</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700/30">
                                    @foreach($variantResult->secondaryMetricSummaries as $i => $secondary)
                                        <tr>
                                            <td class="px-6 py-3 font-mono text-xs text-gray-400">{{ $secondaryBindings[$i]->metric ?? "secondary-$i" }}</td>
                                            <td class="px-6 py-3 text-right text-sm text-gray-300 tabular-nums">{{ number_format($secondary->countOfUnits) }}</td>
                                            <td class="px-6 py-3 text-right text-sm text-gray-300 tabular-nums">{{ number_format($secondary->conversionRate * 100, 2) }}%</td>
                                            <td class="px-6 py-3 text-right text-sm text-gray-300 tabular-nums">{{ number_format($secondary->mean, 4) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach
        @else
            <div class="rounded-lg border border-gray-700 bg-gray-900/50 p-8 text-center">
                <p class="text-sm text-gray-400">No rollup data yet. Results will appear once the rollup job has run.</p>
            </div>
        @endif
    @endif
</div>
