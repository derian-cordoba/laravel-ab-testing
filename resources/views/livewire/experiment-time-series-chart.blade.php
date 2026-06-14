{{--
    Conversion rate time-series chart.

    Architecture notes:
    ─────────────────────────────────────────────────────────────────────────────
    • Data is carried in a hidden <script type="application/json"> element so
      Livewire's morphdom can update it between re-renders without touching the
      chart canvas.
    • The <canvas> is wrapped in wire:ignore — Livewire never replaces it, so
      the Chart.js instance survives server-side re-renders.
    • Chart initialisation is deferred until the accordion is opened (a canvas
      with height:0 produces a zero-dimension Chart.js instance).
    • When the PHP component re-renders in response to 'experiment-updated', it
      dispatches 'chart-data-refreshed' as a browser event *after* the DOM morph
      has committed. Alpine catches this event, updates the hasData flag, and
      destroys/recreates the chart with the fresh data now in the carrier element.
--}}

<div
    x-data="{
        open:    false,
        hasData: @json(!empty($series)),
        _chart:  null,

        _read() {
            try   { return JSON.parse(this.$refs.carrier.textContent); }
            catch { return { series: [], dates: [] }; }
        },

        _init() {
            this._destroy();
            const canvas = this.$refs.canvas;
            if (!canvas) return;

            const { series, dates, boundaries } = this._read();
            if (!dates.length || !series.length) return;

            const datasets = series.map(function(s) {
                return {
                    label:            s.key,
                    data:             s.points,
                    borderColor:      s.color,
                    backgroundColor:  s.color + '1a',
                    borderWidth:      2,
                    pointRadius:      dates.length <= 30 ? 3 : 0,
                    pointHoverRadius: 5,
                    tension:          0.2,
                    fill:             false,
                };
            });

            // O'Brien-Fleming sequential testing boundary lines (only present when
            // target_sample_size is set on the experiment).
            if (boundaries) {
                const boundaryStyle = {
                    borderColor:      'rgba(148,163,184,0.45)',
                    borderDash:       [6, 4],
                    borderWidth:      1.5,
                    pointRadius:      0,
                    pointHoverRadius: 0,
                    fill:             false,
                    tension:          0.2,
                };
                datasets.push(Object.assign({}, boundaryStyle, {
                    label: 'Upper boundary (O\'B-F)',
                    data:  boundaries.upper,
                }));
                datasets.push(Object.assign({}, boundaryStyle, {
                    label: 'Lower boundary (O\'B-F)',
                    data:  boundaries.lower,
                }));
            }

            this._chart = new Chart(canvas, {
                type: 'line',
                data: {
                    labels:   dates.map(function(d) { return d.slice(5); }),
                    datasets: datasets,
                },
                options: {
                    animation:            false,
                    responsive:           true,
                    maintainAspectRatio:  false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'top',
                            align:    'start',
                            labels: {
                                color:           '#9ca3af',
                                padding:         16,
                                boxWidth:        10,
                                font:            { size: 11 },
                                usePointStyle:   true,
                                pointStyleWidth: 8,
                                filter: function(item) {
                                    return !item.text.includes('boundary');
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
                                return !item.dataset.label.includes('boundary');
                            },
                            callbacks: {
                                label: function(ctx) {
                                    return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toFixed(2) + '%';
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            ticks:  { color: '#6b7280', font: { size: 11 }, maxRotation: 0, maxTicksLimit: 8 },
                            grid:   { color: 'rgba(255,255,255,0.04)' },
                            border: { color: 'rgba(255,255,255,0.06)' },
                        },
                        y: {
                            ticks: {
                                color:    '#6b7280',
                                font:     { size: 11 },
                                callback: function(v) { return v + '%'; },
                            },
                            grid:        { color: 'rgba(255,255,255,0.04)' },
                            border:      { color: 'rgba(255,255,255,0.06)' },
                            beginAtZero: true,
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
    @chart-data-refreshed.window="
        hasData = $event.detail.hasData;
        if (open) {
            if (hasData) { $nextTick(function() { _destroy(); _init(); }); }
            else         { _destroy(); }
        }
    "
    class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900"
>
    {{-- Hidden data carrier — morphdom keeps this in sync; Alpine reads it on demand --}}
    <script type="application/json" x-ref="carrier">
        @json(['series' => $series, 'dates' => $dates, 'boundaries' => $boundaries])
    </script>

    {{-- ── Accordion header ───────────────────────────────────────────────── --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">

        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Conversion Rate Over Time</h2>
            @if(!empty($series))
                <span class="text-xs text-gray-500">
                    cumulative &middot; {{ count($dates) }} {{ count($dates) === 1 ? 'day' : 'days' }}
                </span>
            @else
                <span class="text-xs text-gray-500">time series</span>
            @endif
        </div>

        <svg :class="open ? 'rotate-180' : ''"
             class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
        </svg>
    </div>

    {{-- ── Accordion body ─────────────────────────────────────────────────── --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">

        {{-- Empty state --}}
        <div x-show="!hasData" class="p-8 text-center">
            <p class="text-sm text-gray-400">Not enough data to render a time-series chart yet.</p>
            <p class="mt-1 text-xs text-gray-500">At least two days of exposure events are required.</p>
        </div>

        {{--
            Chart container — wire:ignore prevents Livewire's morphdom from
            replacing the canvas element and destroying the Chart.js instance.
            Alpine still controls visibility via x-show.
        --}}
        <div x-show="hasData" wire:ignore class="px-4 pb-5 pt-1" style="height: 280px;">
            <canvas x-ref="canvas"
                    aria-label="Cumulative conversion rate per variant over time"
                    role="img">
            </canvas>
        </div>
    </div>
</div>
