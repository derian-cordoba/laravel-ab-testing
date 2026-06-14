<div>
    @if($model === null)
        <div class="rounded-lg border border-yellow-700/50 bg-yellow-900/20 p-6">
            <h2 class="text-sm font-medium text-yellow-300">Feature flag not found</h2>
            <p class="mt-1 text-sm text-yellow-400/80">
                No feature flag with this key exists in the database. Enable or disable it from the controls to create its state record.
            </p>
            <a href="{{ route('ab-testing.feature-flags.index') }}" class="mt-3 inline-block text-sm text-yellow-300 underline hover:text-yellow-100">
                Back to feature flags
            </a>
        </div>

        {{-- Controls are shown even when no state record exists yet, so the user can create one. --}}
        <div class="mt-6">
            @livewire('ab-testing::feature-flag-controls', ['flagKey' => request()->route('key')])
        </div>
    @else
        {{-- Breadcrumb --}}
        <nav class="mb-5 flex items-center gap-1.5 text-sm text-gray-500">
            <a href="{{ route('ab-testing.feature-flags.index') }}" class="hover:text-gray-300 transition-colors">Feature Flags</a>
            <svg class="h-3.5 w-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
            <span class="text-gray-300">{{ $displayName }}</span>
        </nav>

        {{-- Header row --}}
        <div class="sm:flex sm:items-start sm:justify-between mb-7">
            <div>
                <h1 class="text-2xl font-semibold text-white">{{ $displayName }}</h1>
                <div x-data="{ copied: false }" class="mt-1 flex items-center gap-1.5">
                    <code class="font-mono text-xs text-gray-500">{{ $model->key }}</code>
                    <button
                        type="button"
                        title="Copy flag key"
                        @click="navigator.clipboard && navigator.clipboard.writeText('{{ $model->key }}'); copied = true; setTimeout(() => copied = false, 1500)"
                        class="rounded p-0.5 text-gray-600 hover:text-gray-300 transition-colors"
                    >
                        <svg x-show="!copied" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                        </svg>
                        <svg x-show="copied" class="h-3.5 w-3.5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2 sm:mt-0">
                <x-ab-testing::flag-status-badge
                    :enabled="$model->is_enabled"
                    :killed="$model->killed_at !== null"
                />
                @if($model->killed_at !== null)
                    <span class="inline-flex items-center rounded-full bg-red-900/60 px-3 py-1 text-sm font-medium text-red-300">
                        Kill switch active
                    </span>
                @endif
            </div>
        </div>

        {{-- State summary — accordion, open by default --}}
        <div x-data="{ open: true }" class="mb-6 overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            <div @click="open = !open"
                 role="button" tabindex="0" @keydown.enter="open = !open"
                 class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
                 :class="open ? 'border-b border-gray-700/60' : ''">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-100">Overview</h2>
                    <span class="text-xs text-gray-500">current state</span>
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
                <div class="grid grid-cols-2 gap-px sm:grid-cols-4 bg-gray-700/40">
                    <div class="bg-gray-900 px-6 py-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Status</p>
                        <p class="mt-1.5 text-lg font-semibold {{ $model->is_enabled ? 'text-green-400' : 'text-gray-400' }}">
                            {{ $model->is_enabled ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>
                    <div class="bg-gray-900 px-6 py-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Rollout</p>
                        <p class="mt-1.5 text-lg font-semibold text-gray-100 tabular-nums">{{ $model->rollout_percentage }}%</p>
                    </div>
                    <div class="bg-gray-900 px-6 py-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Conditions</p>
                        @php $conditionCount = count($model->conditions ?? []); @endphp
                        <p class="mt-1.5 text-lg font-semibold {{ $conditionCount > 0 ? 'text-violet-400' : 'text-gray-600' }}">
                            {{ $conditionCount > 0 ? $conditionCount . ' rule' . ($conditionCount > 1 ? 's' : '') : 'None' }}
                        </p>
                    </div>
                    <div class="bg-gray-900 px-6 py-5">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Last Changed</p>
                        <p class="mt-1.5 text-sm font-medium text-gray-300">{{ $model->updated_at?->diffForHumans() ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Controls sub-component --}}
        @livewire('ab-testing::feature-flag-controls', ['flagKey' => $model->key])
    @endif
</div>
