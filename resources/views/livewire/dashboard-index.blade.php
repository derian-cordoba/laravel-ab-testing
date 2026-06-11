<script>
    window._abDashboard = {
        statusChart:      @json($statusChart),
        flagsChart:       @json($flagsChart),
        trafficChart:     @json($trafficChart),
        assignmentsChart: @json($assignmentsChart),
    };

    function abDashboardCharts() {
        return {
            _charts: {},

            _make(key, canvas, config) {
                if (this._charts[key]) { this._charts[key].destroy(); }
                this._charts[key] = new Chart(canvas, config);
            },

            _doughnut(labels, data, colors) {
                return {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{ data: data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: '#9ca3af', padding: 14, boxWidth: 11, font: { size: 11 } },
                            },
                        },
                    },
                };
            },

            _hbar(labels, data, borderColor, bgColor) {
                return {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: bgColor,
                            borderColor: borderColor,
                            borderWidth: 1,
                            borderRadius: 3,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: {
                            x: {
                                ticks: { color: '#6b7280', font: { size: 11 } },
                                grid:  { color: 'rgba(255,255,255,0.04)' },
                                border: { color: 'rgba(255,255,255,0.06)' },
                            },
                            y: {
                                ticks: { color: '#9ca3af', font: { size: 11 } },
                                grid:  { display: false },
                                border: { display: false },
                            },
                        },
                    },
                };
            },

            initCharts() {
                const d = window._abDashboard;

                if (d.statusChart.data.some(function(v) { return v > 0; }) && this.$refs.statusCanvas) {
                    this._make('status', this.$refs.statusCanvas,
                        this._doughnut(d.statusChart.labels, d.statusChart.data, d.statusChart.colors));
                }

                if (d.flagsChart.data.some(function(v) { return v > 0; }) && this.$refs.flagsCanvas) {
                    this._make('flags', this.$refs.flagsCanvas,
                        this._doughnut(d.flagsChart.labels, d.flagsChart.data, d.flagsChart.colors));
                }

                if (d.trafficChart.labels.length && this.$refs.trafficCanvas) {
                    var trafficConfig = this._hbar(
                        d.trafficChart.labels, d.trafficChart.data,
                        'rgba(74,222,128,0.8)', 'rgba(74,222,128,0.25)'
                    );
                    trafficConfig.options.scales.x.min = 0;
                    trafficConfig.options.scales.x.max = 100;
                    trafficConfig.options.scales.x.ticks.callback = function(v) { return v + '%'; };
                    this._make('traffic', this.$refs.trafficCanvas, trafficConfig);
                }

                if (d.assignmentsChart.labels.length && this.$refs.assignCanvas) {
                    this._make('assignments', this.$refs.assignCanvas,
                        this._hbar(
                            d.assignmentsChart.labels, d.assignmentsChart.data,
                            'rgba(139,92,246,0.8)', 'rgba(139,92,246,0.25)'
                        ));
                }
            },
        };
    }
</script>

<div x-data="abDashboardCharts()" x-init="$nextTick(function() { initCharts(); })">

    {{-- Page header --}}
    <div class="mb-7">
        <h1 class="text-2xl font-semibold text-white">Overview</h1>
        <p class="mt-1 text-sm text-gray-400">System-wide health and activity at a glance.</p>
    </div>

    {{-- ── Stat cards ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-8">

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Experiments</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-white">{{ number_format($totalExperiments) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                <span class="font-medium text-green-400">{{ $runningCount }} running</span>
            </p>
        </div>

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Running Now</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-white">{{ $runningCount }}</p>
            <p class="mt-1 text-xs text-gray-500">of {{ number_format($totalExperiments) }} total</p>
        </div>

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Feature Flags</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-white">{{ number_format($totalFlags) }}</p>
            <p class="mt-1 text-xs text-gray-500">
                <span class="font-medium text-green-400">{{ $enabledFlagsCount }} enabled</span>
            </p>
        </div>

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-5">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Assignments</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums text-white">{{ number_format($totalAssignments) }}</p>
            <p class="mt-1 text-xs text-gray-500">total across all experiments</p>
        </div>

    </div>

    {{-- ── Donut charts row ─────────────────────────────────────── --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 mb-6">

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6">
            <h2 class="mb-1 text-sm font-semibold text-gray-300">Experiments by Status</h2>
            <p class="mb-5 text-xs text-gray-600">Distribution across all lifecycle states.</p>
            @if($totalExperiments > 0)
                <div class="relative h-56">
                    <canvas x-ref="statusCanvas"></canvas>
                </div>
            @else
                <div class="flex h-56 items-center justify-center text-sm text-gray-600">
                    No experiments recorded yet.
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6">
            <h2 class="mb-1 text-sm font-semibold text-gray-300">Feature Flags Health</h2>
            <p class="mb-5 text-xs text-gray-600">Enabled, disabled, and kill-switched flags.</p>
            @if($totalFlags > 0)
                <div class="relative h-56">
                    <canvas x-ref="flagsCanvas"></canvas>
                </div>
            @else
                <div class="flex h-56 items-center justify-center text-sm text-gray-600">
                    No feature flags recorded yet.
                </div>
            @endif
        </div>

    </div>

    {{-- ── Running experiments — traffic distribution ───────────── --}}
    @if(count($trafficChart['labels']) > 0)
        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 mb-6">
            <h2 class="mb-1 text-sm font-semibold text-gray-300">Running Experiments — Traffic</h2>
            <p class="mb-5 text-xs text-gray-600">Current traffic percentage allocated to each running experiment.</p>
            <div class="relative" style="height: {{ max(120, count($trafficChart['labels']) * 36) }}px">
                <canvas x-ref="trafficCanvas"></canvas>
            </div>
        </div>
    @endif

    {{-- ── Top experiments by assignment volume ────────────────── --}}
    @if(count($assignmentsChart['labels']) > 0)
        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6">
            <h2 class="mb-1 text-sm font-semibold text-gray-300">Top Experiments by Assignments</h2>
            <p class="mb-5 text-xs text-gray-600">Total units assigned across the highest-volume experiments.</p>
            <div class="relative" style="height: {{ max(120, count($assignmentsChart['labels']) * 36) }}px">
                <canvas x-ref="assignCanvas"></canvas>
            </div>
        </div>
    @endif

    {{-- Empty state --}}
    @if($totalExperiments === 0 && $totalFlags === 0)
        <div class="mt-4 rounded-lg border border-dashed border-gray-700 p-10 text-center">
            <p class="text-sm text-gray-400">Nothing here yet.</p>
            <p class="mt-1 text-xs text-gray-600">
                Register experiments and feature flags in your code and run
                <code class="text-violet-400">php artisan ab:cache</code> to get started.
            </p>
        </div>
    @endif

</div>
