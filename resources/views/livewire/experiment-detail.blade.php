<div @if($isRunning) wire:poll.30s="pollRefresh" @endif>
    @if($model === null)
        <div class="rounded-lg border border-yellow-700/50 bg-yellow-900/20 p-6">
            <h2 class="text-sm font-medium text-yellow-300">Experiment not found</h2>
            <p class="mt-1 text-sm text-yellow-400/80">
                No experiment with this key exists in the database or registry.
            </p>
            <a href="{{ route('ab-testing.index') }}" class="mt-3 inline-block text-sm text-yellow-300 underline hover:text-yellow-100">
                Back to overview
            </a>
        </div>
    @else
        {{-- Breadcrumb --}}
        <nav class="mb-5 flex items-center gap-1.5 text-sm text-gray-500">
            <a href="{{ route('ab-testing.experiments.index') }}" class="hover:text-gray-300 transition-colors">Experiments</a>
            <svg class="h-3.5 w-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-gray-300">{{ $displayName }}</span>
        </nav>

        {{-- Header row --}}
        <div class="sm:flex sm:items-start sm:justify-between mb-7">
            <div>
                <h1 class="text-2xl font-semibold text-white">{{ $displayName }}</h1>
                <p class="mt-1 font-mono text-xs text-gray-500">{{ $model->key }}</p>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 sm:mt-0">
                <x-ab-testing::status-badge :status="$model->status" />
                @if($model->is_killed)
                    <span class="inline-flex items-center rounded-full bg-red-900/60 px-3 py-1 text-sm font-medium text-red-300">
                        Kill switch active
                    </span>
                @endif
                @if($model->layer)
                    <span class="inline-flex items-center rounded bg-gray-800 border border-gray-700 px-2.5 py-1 text-xs text-gray-400">
                        layer: <span class="ml-1 font-mono text-gray-300">{{ $model->layer }}</span>
                    </span>
                @endif
                <span class="inline-flex items-center rounded bg-gray-800 border border-gray-700 px-2.5 py-1 text-xs tabular-nums text-gray-400">
                    {{ $model->traffic_percentage }}% traffic
                </span>
                @if($model->started_at)
                    <span class="inline-flex items-center rounded bg-gray-800 border border-gray-700 px-2.5 py-1 text-xs text-gray-500" title="{{ $model->started_at->toDateTimeString() }} UTC">
                        Started {{ $model->started_at->diffForHumans() }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Guardrail breach alert strip (shown when there are active unacknowledged breaches) --}}
        @if($activeBreachCount > 0)
            <div class="mb-5 flex items-start gap-3 rounded-lg border border-red-700/60 bg-red-900/20 px-5 py-4">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                </svg>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-red-300">
                        {{ $activeBreachCount }} active guardrail {{ $activeBreachCount === 1 ? 'breach' : 'breaches' }}
                    </p>
                    <p class="mt-0.5 text-sm text-red-400/80">
                        One or more guardrail metrics have exceeded their maximum allowed regression.
                        Review the Results section below and consider pausing this experiment.
                    </p>
                </div>
                @if($isRunning)
                    <a href="#results"
                       class="shrink-0 rounded-md border border-red-700 bg-red-900/40 px-3 py-1.5 text-xs font-medium text-red-300 hover:bg-red-800/50 transition-colors">
                        Review
                    </a>
                @endif
            </div>
        @endif

        {{-- Live polling indicator (only shown when running) --}}
        @if($isRunning)
            <div class="mb-5 flex items-center gap-2 text-xs text-gray-600">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-green-500"></span>
                </span>
                Live — refreshing every 30 s
            </div>
        @endif

        {{-- Verdict banner — full width --}}
        <div class="mb-6" id="results">
            @livewire('ab-testing::experiment-verdict-banner', ['experimentKey' => $model->key])
        </div>

        {{-- Results table — full width, open by default --}}
        <div class="mb-6">
            @livewire('ab-testing::experiment-results-table', ['experimentKey' => $model->key])
        </div>

        {{-- Controls + Variants — two columns --}}
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                @livewire('ab-testing::experiment-controls', ['experimentKey' => $model->key])
            </div>
            <div>
                @livewire('ab-testing::experiment-variant-manager', ['experimentKey' => $model->key])
            </div>
        </div>

        {{-- Chart — full width, collapsed by default --}}
        <div class="mb-6">
            @livewire('ab-testing::experiment-time-series-chart', ['experimentKey' => $model->key])
        </div>

        {{-- Trust — full width, collapsed by default --}}
        <div class="mb-6">
            @livewire('ab-testing::experiment-trust-panel', ['experimentKey' => $model->key])
        </div>

        {{-- Power Analysis + Export — two columns, both collapsed by default --}}
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                @livewire('ab-testing::experiment-power-analysis', ['experimentKey' => $model->key])
            </div>
            <div>
                @livewire('ab-testing::experiment-export', ['experimentKey' => $model->key])
            </div>
        </div>

        {{-- Settings + Approval — two columns --}}
        <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div>
                @livewire('ab-testing::edit-experiment', ['experimentKey' => $model->key])
            </div>
            <div>
                @livewire('ab-testing::experiment-approval-panel', ['experimentKey' => $model->key])
            </div>
        </div>

        {{-- Audit log — full width --}}
        @if($auditLog->isNotEmpty())
            <div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
                <div @click="open = !open"
                     role="button" tabindex="0" @keydown.enter="open = !open"
                     class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
                     :class="open ? 'border-b border-gray-700/60' : ''">
                    <div class="flex items-center gap-3">
                        <h2 class="font-semibold text-gray-100">Audit Log</h2>
                        <span class="text-xs text-gray-500">{{ $auditLog->count() }} {{ $auditLog->count() === 1 ? 'entry' : 'entries' }}</span>
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
                    <table class="min-w-full divide-y divide-gray-700/60">
                        <thead>
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Actor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Before</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">After</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700/40">
                            @foreach($auditLog as $entry)
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center rounded bg-gray-700/70 px-2 py-0.5 font-mono text-xs text-gray-300">
                                            {{ $entry->action }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-sm text-gray-400">
                                        {{ $entry->actor_identifier }}
                                        @if($entry->actor_type !== 'user')
                                            <span class="text-xs text-gray-600">({{ $entry->actor_type }})</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-xs text-gray-500 font-mono">
                                        {{ $entry->before_state ? collect($entry->before_state)->map(fn($v, $k) => "$k: $v")->implode(', ') : '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-xs text-gray-400 font-mono">
                                        {{ $entry->after_state ? collect($entry->after_state)->map(fn($v, $k) => "$k: $v")->implode(', ') : '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-right text-xs text-gray-500" title="{{ $entry->occurred_at }}">
                                        {{ $entry->occurred_at->diffForHumans() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
