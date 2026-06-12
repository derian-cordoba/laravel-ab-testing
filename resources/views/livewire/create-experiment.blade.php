<div>
    {{-- Breadcrumb --}}
    <nav class="mb-5 flex items-center gap-1.5 text-sm text-gray-500">
        <a href="{{ route('ab-testing.experiments.index') }}" class="hover:text-gray-300 transition-colors">Experiments</a>
        <svg class="h-3.5 w-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-300">New Experiment</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">New Experiment</h1>
        <p class="mt-1 text-sm text-gray-400">Create a runtime-defined experiment. Add variants from the detail page after creation.</p>
    </div>

    @if($flashMessage)
        <x-ab-testing::flash-message :message="$flashMessage" :type="$flashType" class="mb-6" />
    @endif

    <form wire:submit="save">
        <div class="rounded-lg border border-gray-700 bg-gray-900 divide-y divide-gray-700/60">

            {{-- Key --}}
            <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300">Key <span class="text-red-400">*</span></label>
                    <p class="mt-1 text-xs text-gray-500">Unique, kebab-case identifier. Cannot be changed after creation.</p>
                </div>
                <div class="sm:col-span-2">
                    <input
                        type="text"
                        wire:model="key"
                        placeholder="e.g. checkout-button-color"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    />
                    @error('key')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-600">Lowercase letters, numbers, and hyphens only.</p>
                </div>
            </div>

            {{-- Name --}}
            <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300">Display Name</label>
                    <p class="mt-1 text-xs text-gray-500">Optional human-readable label shown in the dashboard.</p>
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
                    <p class="mt-1 text-xs text-gray-500">Mutual-exclusion namespace. A unit enters at most one running experiment per layer.</p>
                </div>
                <div class="sm:col-span-2">
                    <input
                        type="text"
                        wire:model="layer"
                        placeholder="e.g. checkout"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    />
                    @error('layer')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Traffic percentage --}}
            <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300">Initial Traffic %</label>
                    <p class="mt-1 text-xs text-gray-500">Percentage of eligible units that enter the experiment. Can be ramped after start.</p>
                </div>
                <div class="sm:col-span-2">
                    <div class="flex items-center gap-4">
                        <input
                            type="range"
                            wire:model.live="trafficPercentage"
                            min="0"
                            max="100"
                            step="5"
                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-700 accent-violet-500"
                        />
                        <span class="w-12 shrink-0 text-right tabular-nums text-sm font-medium text-gray-200">{{ $trafficPercentage }}%</span>
                    </div>
                    @error('trafficPercentage')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>

        {{-- Actions --}}
        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('ab-testing.experiments.index') }}"
               class="rounded-md border border-gray-600 bg-gray-800 px-4 py-2 text-sm text-gray-300 hover:border-gray-500 hover:text-gray-100 transition-colors">
                Cancel
            </a>
            <button
                type="submit"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-md bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-500 disabled:opacity-60 transition-colors">
                <svg wire:loading class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Create Experiment
            </button>
        </div>
    </form>
</div>
