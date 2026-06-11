<div>
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
            <a href="{{ route('ab-testing.index') }}" class="hover:text-gray-300 transition-colors">Experiments</a>
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
            </div>
        </div>

        {{-- Results table sub-component --}}
        @livewire('ab-testing::experiment-results-table', ['experimentKey' => $model->key])

        {{-- Controls sub-component --}}
        <div class="mt-8">
            <h2 class="mb-4 text-lg font-semibold text-gray-100">Controls</h2>
            @livewire('ab-testing::experiment-controls', ['experimentKey' => $model->key])
        </div>

        {{-- Audit log --}}
        @if($auditLog->isNotEmpty())
            <div class="mt-8">
                <h2 class="mb-4 text-lg font-semibold text-gray-100">Audit Log</h2>
                <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
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
