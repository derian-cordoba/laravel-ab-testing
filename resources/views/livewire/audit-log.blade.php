<div>
    <div class="mb-6 sm:flex sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-white">Audit Log</h1>
            <p class="mt-1 text-sm text-gray-400">All privileged actions recorded across every experiment.</p>
        </div>
        <span class="mt-3 sm:mt-0 text-xs text-gray-600 tabular-nums">{{ number_format($total) }} {{ $total === 1 ? 'entry' : 'entries' }}</span>
    </div>

    {{-- Filters --}}
    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input wire:model.live.debounce.300ms="experimentFilter"
                   type="search" placeholder="Filter by experiment key…"
                   class="w-full rounded-lg border border-gray-700 bg-gray-900 py-2 pl-9 pr-4 text-sm text-gray-200 placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
        </div>

        <select wire:model.live="actionFilter"
                class="rounded-lg border border-gray-700 bg-gray-900 py-2 pl-3 pr-8 text-sm text-gray-200 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
            <option value="">All actions</option>
            @foreach($distinctActions as $action)
                <option value="{{ $action }}">{{ $action }}</option>
            @endforeach
        </select>

        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
            </svg>
            <input wire:model.live.debounce.300ms="actorFilter"
                   type="search" placeholder="Filter by actor…"
                   class="w-full rounded-lg border border-gray-700 bg-gray-900 py-2 pl-9 pr-4 text-sm text-gray-200 placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
        </div>
    </div>

    @if($entries->isEmpty())
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-10 text-center">
            <p class="text-sm text-gray-400">No audit log entries match your filters.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700/60">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Time</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Action</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Experiment</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Actor</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Before</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/40">
                        @foreach($entries as $entry)
                            <tr class="hover:bg-gray-800/30 transition-colors">
                                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap" title="{{ $entry->occurred_at }}">
                                    {{ $entry->occurred_at->diffForHumans() }}
                                </td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center rounded bg-gray-700/70 px-2 py-0.5 font-mono text-xs text-gray-300">
                                        {{ $entry->action }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    @if($entry->experiment_key)
                                        <a href="{{ route('ab-testing.experiments.show', $entry->experiment_key) }}"
                                           class="font-mono text-xs text-violet-400 hover:text-violet-200 transition-colors">
                                            {{ $entry->experiment_key }}
                                        </a>
                                    @else
                                        <span class="text-gray-600 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-400">
                                    {{ $entry->actor_identifier ?? '—' }}
                                    @if($entry->actor_type && $entry->actor_type !== 'user')
                                        <span class="text-xs text-gray-600">({{ $entry->actor_type }})</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500 max-w-xs truncate">
                                    {{ $entry->before_state ? collect($entry->before_state)->map(fn($v, $k) => "$k: $v")->implode(', ') : '—' }}
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-400 max-w-xs truncate">
                                    {{ $entry->after_state ? collect($entry->after_state)->map(fn($v, $k) => "$k: $v")->implode(', ') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($hasMore)
                <div class="border-t border-gray-700/60 px-5 py-4 text-center">
                    <button wire:click="loadMore" type="button"
                            class="rounded-md border border-gray-700 bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:border-gray-600 hover:text-white transition-colors">
                        Load more
                        <span wire:loading.inline class="ml-1 text-gray-500">…</span>
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
