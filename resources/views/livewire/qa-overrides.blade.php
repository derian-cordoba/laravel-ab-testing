<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">QA Overrides</h1>
        <p class="mt-1 text-sm text-gray-400">
            Force a specific variant assignment for any unit, bypassing deterministic bucketing.
            Deleting a row restores natural assignment on the unit's next resolution.
        </p>
    </div>

    @if(session('override-success'))
        <div class="mb-5 flex items-center gap-3 rounded-lg border border-green-700/50 bg-green-900/20 px-4 py-3">
            <svg class="h-4 w-4 shrink-0 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
            </svg>
            <p class="text-sm text-green-300">{{ session('override-success') }}</p>
        </div>
    @endif

    {{-- ── Set Override form ──────────────────────────────────────────────── --}}
    <div class="mb-8 rounded-lg border border-gray-700 bg-gray-900 p-6">
        <h2 class="mb-4 text-sm font-semibold text-gray-300">Set Override</h2>

        <form wire:submit="setOverride" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

            <div class="lg:col-span-1">
                <label class="mb-1.5 block text-xs font-medium text-gray-400">Experiment</label>
                <select wire:model.live="newExperimentKey"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 py-2 pl-3 pr-8 text-sm text-gray-200 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
                    <option value="">Select experiment…</option>
                    @foreach($experimentsForSelect as $exp)
                        <option value="{{ $exp['key'] }}">{{ $exp['label'] }}</option>
                    @endforeach
                </select>
                @error('newExperimentKey') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-gray-400">Unit Type</label>
                <select wire:model="newUnitType"
                        class="w-full rounded-lg border border-gray-700 bg-gray-800 py-2 pl-3 pr-8 text-sm text-gray-200 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
                    @foreach(['user', 'tenant', 'session', 'device'] as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-gray-400">Unit Key</label>
                <input wire:model="newUnitKey" type="text" placeholder="e.g. user:42"
                       class="w-full rounded-lg border border-gray-700 bg-gray-800 py-2 px-3 text-sm text-gray-200 placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
                @error('newUnitKey') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1.5 block text-xs font-medium text-gray-400">Variant</label>
                @if($variantsForSelect->isNotEmpty())
                    <select wire:model="newVariantKey"
                            class="w-full rounded-lg border border-gray-700 bg-gray-800 py-2 pl-3 pr-8 text-sm text-gray-200 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
                        <option value="">Select variant…</option>
                        @foreach($variantsForSelect as $variant)
                            <option value="{{ $variant }}">{{ $variant }}</option>
                        @endforeach
                    </select>
                @else
                    <input wire:model="newVariantKey" type="text" placeholder="e.g. control"
                           class="w-full rounded-lg border border-gray-700 bg-gray-800 py-2 px-3 text-sm text-gray-200 placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
                @endif
                @error('newVariantKey') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2 lg:col-span-4 flex justify-end">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 transition-colors disabled:opacity-50">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Set Override
                    <span wire:loading.inline wire:target="setOverride">…</span>
                </button>
            </div>
        </form>
    </div>

    {{-- ── Assignment list ─────────────────────────────────────────────────── --}}
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-sm font-semibold text-gray-300">
            Current Assignments
            <span class="ml-1.5 font-normal text-gray-600">({{ number_format($total) }})</span>
        </h2>
        <div class="flex gap-2">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-500"
                     fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search unit key…"
                       class="w-52 rounded-lg border border-gray-700 bg-gray-900 py-1.5 pl-9 pr-3 text-sm text-gray-200 placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
            </div>
            <input wire:model.live.debounce.300ms="experimentSearch" type="search" placeholder="Experiment…"
                   class="w-44 rounded-lg border border-gray-700 bg-gray-900 py-1.5 px-3 text-sm text-gray-200 placeholder-gray-600 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500/40">
        </div>
    </div>

    @if($assignments->isEmpty())
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-8 text-center">
            <p class="text-sm text-gray-400">No assignments found.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700/60">
                    <thead>
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Experiment</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Unit Key</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Unit Type</th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Assigned Variant</th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Assigned</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/40">
                        @foreach($assignments as $assignment)
                            <tr class="hover:bg-gray-800/30 transition-colors">
                                <td class="px-5 py-3">
                                    <a href="{{ route('ab-testing.experiments.show', $assignment->experiment_key) }}"
                                       class="font-mono text-xs text-violet-400 hover:text-violet-200 transition-colors">
                                        {{ $assignment->experiment_key }}
                                    </a>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-300 max-w-xs truncate">
                                    {{ $assignment->unit_key }}
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500">{{ $assignment->unit_type }}</td>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 rounded border border-violet-700/60 bg-violet-900/30 px-2 py-0.5 font-mono text-xs text-violet-300">
                                        {{ $assignment->variant_key }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 text-right text-xs text-gray-500 whitespace-nowrap"
                                    title="{{ $assignment->assigned_at }}">
                                    {{ $assignment->assigned_at->diffForHumans() }}
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <button wire:click="removeOverride('{{ $assignment->experiment_key }}', '{{ $assignment->unit_type }}', '{{ $assignment->unit_key }}')"
                                            wire:confirm="Remove this assignment? The unit will be re-bucketed on next resolution."
                                            type="button"
                                            class="rounded px-2 py-1 text-xs text-gray-600 hover:bg-red-900/30 hover:text-red-400 transition-colors">
                                        Remove
                                    </button>
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
                        <span wire:loading.inline>…</span>
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
