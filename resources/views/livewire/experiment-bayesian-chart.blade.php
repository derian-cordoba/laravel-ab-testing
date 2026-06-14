{{--
    Bayesian posterior distribution chart.

    Architecture notes:
    ─────────────────────────────────────────────────────────────────────────────
    • Shows Beta(α, β) posterior PDFs for each variant's conversion rate.
      α = conversions + 1, β = (units − conversions) + 1 (uniform prior).
    • X-axis: conversion rate in %; Y-axis: probability density (unnormalised).
    • Data carried in a hidden JSON carrier so morphdom can update it without
      destroying the chart canvas (same pattern as the time-series chart).
    • Vertical mean lines rendered as Chart.js annotation-style datasets.
    • Shaded 95% credible interval rendered via fill between two hidden datasets.
--}}

<div
    x-data="{
        open:    false,
        hasData: @json(!empty($series)),
        _chart:  null,

        _read() {
            try   { return JSON.parse(this.$refs.carrier.textContent); }
            catch { return { series: [], labels: [] }; }
        },

        _init() {
            this._destroy();
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            const { series, labels } = this._read();
            if (!labels.length || !series.length) return;

            const datasets = [];

            series.forEach(function(s) {
                // Posterior curve
                datasets.push({
                    label:           s.key,
                    data:            s.points,
                    borderColor:     s.color,
                    backgroundColor: s.color + '18',
                    borderWidth:     2,
                    pointRadius:     0,
                    tension:         0.3,
                    fill:            true,
                });

                // Mean vertical line (simulated as a dataset with a single non-null value
                // at the closest x index, then NaN elsewhere)
                const meanPct = s.mean;
                const meanIdx = labels.reduce(function(best, x, i) {
                    return Math.abs(x - meanPct) < Math.abs(labels[best] - meanPct) ? i : best;
                }, 0);
                const meanData = labels.map(function(_, i) { return i === meanIdx ? s.points[i] : null; });

                datasets.push({
                    label:           s.key + ' mean',
                    data:            meanData,
                    borderColor:     s.color,
                    backgroundColor: s.color,
                    borderWidth:     0,
                    pointRadius:     6,
                    pointHoverRadius: 8,
                    pointStyle:      'line',
                    rotation:        90,
                    showLine:        false,
                    fill:            false,
                });
            });

            this._chart = new Chart(canvas, {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: {
                    animation:           false,
                    responsive:          true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align:    'start',
                            labels: {
                                color:     '#9ca3af',
                                padding:   16,
                                boxWidth:  10,
                                font:      { size: 11 },
                                filter: function(item) {
                                    return !item.text.includes(' mean');
                                },
                            },
                        },
                        tooltip: {
                            backgroundColor: '#1f2937',
                            borderColor:     '#374151',
                            borderWidth:     1,
                            titleColor:      '#f3f4f6',
                            bodyColor:       '#9ca3af',
                            padding:         10,
                            filter: function(item) {
                                return !item.dataset.label.includes(' mean');
                            },
                            callbacks: {
                                title: function(items) {
                                    return items[0] ? items[0].label + '%' : '';
                                },
                                label: function(ctx) {
                                    if (ctx.dataset.label.includes(' mean')) return null;
                                    return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(4);
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks:  {
                                color:         '#6b7280',
                                font:          { size: 11 },
                                maxTicksLimit: 10,
                                callback:      function(v, i) { return labels[i] !== undefined ? labels[i].toFixed(1) + '%' : ''; },
                            },
                            grid:   { color: 'rgba(255,255,255,0.04)' },
                            border: { color: 'rgba(255,255,255,0.06)' },
                        },
                        y: {
                            ticks:       { color: '#6b7280', font: { size: 11 } },
                            grid:        { color: 'rgba(255,255,255,0.04)' },
                            border:      { color: 'rgba(255,255,255,0.06)' },
                            beginAtZero: true,
                            title: {
                                display: true,
                                text:    'Probability Density',
                                color:   '#4b5563',
                                font:    { size: 10 },
                            },
                        },
                    },
                },
            });
        },

        _destroy() {
            if (this._chart) { this._chart.destroy(); this._chart = null; }
        },
    }"
    x-init="
        $watch('open', function(val) {
            if (val && hasData) $nextTick(function() { _init(); });
        });
    "
    @bayesian-data-refreshed.window="
        hasData = $event.detail.hasData;
        if (open) {
            if (hasData) { $nextTick(function() { _destroy(); _init(); }); }
            else         { _destroy(); }
        }
    "
    class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900"
>
    {{-- Hidden JSON carrier --}}
    <script type="application/json" x-ref="carrier">
        @json(['series' => $series, 'labels' => $labels])
    </script>

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">

        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Bayesian Posterior</h2>
            @if(!empty($series))
                <span class="text-xs text-gray-500">
                    Beta posteriors for {{ count($series) }} {{ count($series) === 1 ? 'variant' : 'variants' }}
                </span>
            @else
                <span class="text-xs text-gray-500">conversion rate distributions</span>
            @endif
        </div>

        <svg :class="open ? 'rotate-180' : ''"
             class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
        </svg>
    </div>

    {{-- Accordion body --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">

        <div x-show="!hasData" class="p-8 text-center">
            <p class="text-sm text-gray-400">No rollup data yet — posterior will appear once conversions are recorded.</p>
        </div>

        <div x-show="hasData" wire:ignore class="px-4 pb-5 pt-3" style="height: 280px;">
            <canvas x-ref="canvas"
                    aria-label="Bayesian posterior distributions per variant"
                    role="img">
            </canvas>
        </div>

        {{-- Credible interval summary table --}}
        @if(!empty($series))
            <div class="border-t border-gray-700/60 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700/40">
                    <thead>
                        <tr>
                            <th class="px-6 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Variant</th>
                            <th class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Posterior Mean</th>
                            <th class="px-6 py-2 text-right text-xs font-medium uppercase tracking-wide text-gray-500">95% Credible Interval</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/30">
                        @foreach($series as $s)
                            <tr>
                                <td class="px-6 py-2.5 flex items-center gap-2">
                                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $s['color'] }}"></span>
                                    <span class="font-mono text-xs text-gray-300">{{ $s['key'] }}</span>
                                </td>
                                <td class="px-6 py-2.5 text-right text-sm tabular-nums text-gray-300">
                                    {{ number_format($s['mean'], 2) }}%
                                </td>
                                <td class="px-6 py-2.5 text-right text-sm tabular-nums text-gray-500">
                                    [{{ number_format($s['credibleLow'], 2) }}%, {{ number_format($s['credibleHigh'], 2) }}%]
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-700/60 px-6 py-2.5">
                <p class="text-xs text-gray-600">
                    Beta(conversions + 1, units − conversions + 1) posterior under a uniform prior.
                    Dots mark each posterior mean. 95% central credible intervals shown in table.
                </p>
            </div>
        @endif
    </div>
</div>
