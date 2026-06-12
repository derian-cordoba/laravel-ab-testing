<div>
    {{-- Breadcrumb --}}
    <nav class="mb-5 flex items-center gap-1.5 text-sm text-gray-500">
        <a href="{{ route('ab-testing.feature-flags.index') }}" class="hover:text-gray-300 transition-colors">Feature Flags</a>
        <svg class="h-3.5 w-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
        <span class="text-gray-300">New Feature Flag</span>
    </nav>

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">New Feature Flag</h1>
        <p class="mt-1 text-sm text-gray-400">Create a new feature flag. Targeting conditions can be added from the detail page after creation.</p>
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
                        placeholder="e.g. new-checkout-flow"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    />
                    @error('key')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-600">Lowercase letters, numbers, and hyphens only.</p>
                </div>
            </div>

            {{-- Enabled --}}
            <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300">Initially Enabled</label>
                    <p class="mt-1 text-xs text-gray-500">Whether the flag is active immediately upon creation.</p>
                </div>
                <div class="sm:col-span-2 flex items-center">
                    <button
                        type="button"
                        wire:click="$toggle('isEnabled')"
                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $isEnabled ? 'bg-violet-600' : 'bg-gray-600' }}"
                        role="switch"
                        aria-checked="{{ $isEnabled ? 'true' : 'false' }}"
                    >
                        <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $isEnabled ? 'translate-x-5' : 'translate-x-0' }}"></span>
                    </button>
                    <span class="ml-3 text-sm {{ $isEnabled ? 'text-green-400' : 'text-gray-500' }}">
                        {{ $isEnabled ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
            </div>

            {{-- Rollout percentage --}}
            <div class="grid grid-cols-1 gap-4 px-6 py-5 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-300">Rollout %</label>
                    <p class="mt-1 text-xs text-gray-500">Percentage of eligible units that will see this flag as enabled.</p>
                </div>
                <div class="sm:col-span-2">
                    <div class="flex items-center gap-4">
                        <input
                            type="range"
                            wire:model.live="rolloutPercentage"
                            min="0"
                            max="100"
                            step="5"
                            class="h-2 w-full cursor-pointer appearance-none rounded-full bg-gray-700 accent-violet-500"
                        />
                        <span class="w-12 shrink-0 text-right tabular-nums text-sm font-medium text-gray-200">{{ $rolloutPercentage }}%</span>
                    </div>
                    @error('rolloutPercentage')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

        </div>

        {{-- Actions --}}
        <div class="mt-6 flex items-center justify-end gap-3">
            <a href="{{ route('ab-testing.feature-flags.index') }}"
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
                Create Feature Flag
            </button>
        </div>
    </form>
</div>
