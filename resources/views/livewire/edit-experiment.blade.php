<div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Settings</h2>
            <span class="text-xs text-gray-500">name, layer, sample size</span>
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

        @if($flashMessage)
            <div class="px-6 pt-4">
                <x-ab-testing::flash-message :message="$flashMessage" :type="$flashType" class="mb-4" />
            </div>
        @endif

        <form wire:submit="save">
            <div class="divide-y divide-gray-700/60">

                {{-- Name --}}
                <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Display Name</label>
                        <p class="mt-1 text-xs text-gray-500">Human-readable label. Takes lower priority than a code-defined name.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="e.g. Checkout Button Color Test"
                            class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                        @error('name')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Layer --}}
                <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Layer</label>
                        <p class="mt-1 text-xs text-gray-500">
                            Mutual-exclusion namespace.
                            @if($layerLocked)
                                <span class="text-amber-400">Locked — experiment is no longer in draft/scheduled status.</span>
                            @endif
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        @if($layerLocked)
                            <p class="rounded-md border border-gray-700 bg-gray-800/50 px-3 py-2 text-sm text-gray-400 font-mono">
                                {{ $layer ?? '—' }}
                            </p>
                        @else
                            <input
                                type="text"
                                wire:model="layer"
                                placeholder="e.g. checkout"
                                class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                            />
                            @error('layer')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                </div>

                {{-- Target sample size --}}
                <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-300">Target Sample Size</label>
                        <p class="mt-1 text-xs text-gray-500">Units required per variant arm. Drives the progress badge. Use the power analysis panel to calculate this.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <input
                            type="number"
                            wire:model="targetSampleSize"
                            placeholder="e.g. 5000"
                            min="1"
                            class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                        />
                        @error('targetSampleSize')
                            <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            </div>

            <div class="px-6 py-4 flex justify-end border-t border-gray-700/60">
                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-60 transition-colors">
                    <svg wire:loading class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
