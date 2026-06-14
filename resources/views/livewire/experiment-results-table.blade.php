@use(ABTests\Application\Data\ExperimentResultsData)
@use(ABTests\Application\Data\VariantResultData)
@use(ABTests\Enums\MetricRole)

<div x-data="{ open: true }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Results</h2>
            @if($results !== null && $results->hasResults())
                <span class="text-xs text-gray-500">
                    {{ number_format($results->totalAssignedUnits()) }} units
                    &middot; computed {{ $results->computedAt->format('H:i') }} UTC
                </span>
            @else
                <span class="text-xs text-gray-500">variant comparison</span>
            @endif
        </div>
        <svg :class="open ? 'rotate-180' : ''"
             class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
        </svg>
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">

        @if($results === null)
            <div class="p-8 text-center">
                <p class="text-sm text-gray-400">No data available for this experiment yet.</p>
            </div>
        @else
            @php
                /** @var ExperimentResultsData $results */
                $definition = $results->definition;
            @endphp

            <div class="px-6 pt-5 pb-1 space-y-4">

                {{-- Active guardrail breaches --}}
                @if($results->activeGuardrailBreaches->isNotEmpty())
                    <div class="rounded-lg border border-red-700/50 bg-red-900/20 p-4">
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
                    <div class="rounded-lg border border-orange-700/50 bg-orange-900/20 p-4">
                        <h3 class="text-sm font-semibold text-orange-300">Sample Ratio Mismatch Detected</h3>
                        <p class="mt-1 text-sm text-orange-400/80">
                            Chi-square: {{ number_format($results->sampleRatioMismatch->chiSquare, 3) }} —
                            p-value: {{ number_format($results->sampleRatioMismatch->pValue, 4) }}.
                            Results may not be valid. Investigate assignment before drawing conclusions.
                        </p>
                    </div>
                @endif
            </div>

            @if($results->hasResults())
                @php
                    // Sample size gauge
                    $targetSampleSize = $results->model->target_sample_size;
                    $currentUnits     = $results->totalAssignedUnits();
                    $samplePct        = $targetSampleSize
                        ? (int) min(100, round($currentUnits / $targetSampleSize * 100))
                        : null;

                    // CI bar scale: find max absolute bound across all treatment variants,
                    // floor at 0.15 so even tiny CIs render visibly.
                    $ciMaxBound = 0.15;
                    foreach ($results->variantResults as $vr) {
                        $interval = $vr->verdictResult?->frequentist?->interval ?? null;
                        if ($interval !== null) {
                            $ciMaxBound = max($ciMaxBound, abs($interval[0]), abs($interval[1]));
                        }
                    }
                    $ciMaxBound = min($ciMaxBound, 1.0);
                @endphp

                {{-- Sample size progress gauge --}}
                @if($samplePct !== null)
                    <div class="border-b border-gray-700/60 px-6 py-3">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs text-gray-500 font-medium uppercase tracking-wide">Sample Size Progress</span>
                            <span class="text-xs tabular-nums text-gray-400">
                                {{ number_format($currentUnits) }} / {{ number_format($targetSampleSize) }}
                                <span class="ml-1.5 {{ $samplePct >= 100 ? 'text-green-400' : 'text-gray-500' }}">{{ $samplePct }}%</span>
                            </span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-700/70 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 {{ $samplePct >= 100 ? 'bg-green-500' : ($samplePct >= 50 ? 'bg-violet-500' : 'bg-violet-600/70') }}"
                                 style="width: {{ $samplePct }}%"></div>
                        </div>
                        @if($samplePct >= 100)
                            <p class="mt-1 text-xs text-green-500/80">Target reached — results are fully powered.</p>
                        @endif
                    </div>
                @endif

                {{-- Primary metric results table --}}
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-700/60">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Variant</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Units</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Conv. Rate</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Lift</th>
                                <th class="px-5 py-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500">95% CI</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">p-value</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Prob. to beat</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Verdict</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/40">
                            @foreach($results->variantResults as $variantResult)
                                @php
                                    $primary   = $variantResult->primaryMetricSummary;
                                    $verdict   = $variantResult->verdictResult;
                                    $isControl = $variantResult->variant->isControl();
                                    $lift      = $verdict?->frequentist?->relativeLift ?? $verdict?->bayesian?->relativeLift;
                                    $interval  = $verdict?->frequentist?->interval ?? null;
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

                                    {{-- 95% CI inline bar chart --}}
                                    <td class="px-5 py-4">
                                        @if($interval !== null && ! $isControl)
                                            @php
                                                // Map relative-lift values to pixel positions [0..120].
                                                // Zero line sits at x=60 (centre). Scale is ±$ciMaxBound.
                                                $svgW    = 120;
                                                $midX    = $svgW / 2;
                                                $toX     = static fn (float $v): float
                                                    => max(0.0, min((float) $svgW, $midX + ($v / $ciMaxBound) * $midX));

                                                $lowerX  = $toX($interval[0]);
                                                $upperX  = $toX($interval[1]);
                                                $liftX   = $toX($lift ?? 0.0);
                                                $barW    = max(2.0, $upperX - $lowerX);

                                                // Colour: green when fully positive, red when fully negative, gray otherwise.
                                                $ciColor = $interval[0] >= 0
                                                    ? '#22c55e'
                                                    : ($interval[1] <= 0 ? '#ef4444' : '#94a3b8');
                                            @endphp
                                            <div class="flex flex-col items-center gap-0.5">
                                                <svg width="{{ $svgW }}" height="18" viewBox="0 0 {{ $svgW }} 18"
                                                     class="overflow-visible">
                                                    {{-- Background track --}}
                                                    <rect x="0" y="8" width="{{ $svgW }}" height="2" rx="1" fill="#1f2937"/>
                                                    {{-- CI interval bar --}}
                                                    <rect x="{{ $lowerX }}" y="6" width="{{ $barW }}" height="6" rx="2"
                                                          fill="{{ $ciColor }}" opacity="0.55"/>
                                                    {{-- Zero line --}}
                                                    <rect x="{{ $midX - 0.75 }}" y="3" width="1.5" height="12" rx="0.5" fill="#4b5563"/>
                                                    {{-- Lift point --}}
                                                    <circle cx="{{ $liftX }}" cy="9" r="3.5" fill="{{ $ciColor }}"/>
                                                    {{-- CI whiskers --}}
                                                    <rect x="{{ $lowerX }}" y="4" width="1.5" height="10" rx="0.5" fill="{{ $ciColor }}" opacity="0.8"/>
                                                    <rect x="{{ $upperX - 1.5 }}" y="4" width="1.5" height="10" rx="0.5" fill="{{ $ciColor }}" opacity="0.8"/>
                                                </svg>
                                                <span class="text-[10px] tabular-nums text-gray-600 leading-none">
                                                    {{ $interval[0] >= 0 ? '+' : '' }}{{ number_format($interval[0] * 100, 1) }}%
                                                    …
                                                    {{ $interval[1] >= 0 ? '+' : '' }}{{ number_format($interval[1] * 100, 1) }}%
                                                </span>
                                            </div>
                                        @else
                                            <div class="flex justify-center">
                                                <span class="text-gray-700 text-sm">—</span>
                                            </div>
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

                {{-- Secondary metrics per variant --}}
                @php
                    $secondaryBindings = array_values(array_filter(
                        $definition->metrics,
                        static fn ($b) => $b->role === MetricRole::secondary
                    ));
                @endphp
                @foreach($results->variantResults as $variantResult)
                    @if(!empty($variantResult->secondaryMetricSummaries))
                        <div class="border-t border-gray-700/60">
                            <div class="px-6 py-3 border-b border-gray-700/40">
                                <h3 class="text-sm font-semibold text-gray-400">
                                    Secondary metrics — <span class="font-mono">{{ $variantResult->variant->key() }}</span>
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
                <div class="p-8 text-center">
                    <p class="text-sm text-gray-400">No rollup data yet. Results will appear once the rollup job has run.</p>
                </div>
            @endif
        @endif
    </div>
</div>
