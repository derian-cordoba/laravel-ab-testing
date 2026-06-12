<div>
    @if($flashMessage)
        <x-ab-testing::flash-message :message="$flashMessage" :type="$flashType" class="mb-4" />
    @endif

    <form wire:submit="save">
        <div class="rounded-lg border border-gray-700 bg-gray-900 divide-y divide-gray-700/60">

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

        {{-- Save button --}}
        <div class="mt-5 flex justify-end">
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
