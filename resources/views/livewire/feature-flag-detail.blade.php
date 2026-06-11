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
        <div class="mt-8">
            <h2 class="mb-4 text-lg font-semibold text-gray-100">Controls</h2>
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
                <p class="mt-1 font-mono text-xs text-gray-500">{{ $model->key }}</p>
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

        {{-- State summary cards --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-8">
            <div class="rounded-lg border border-gray-700 bg-gray-900 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Status</p>
                <p class="mt-1 text-lg font-semibold {{ $model->is_enabled ? 'text-green-400' : 'text-gray-400' }}">
                    {{ $model->is_enabled ? 'Enabled' : 'Disabled' }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-700 bg-gray-900 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Rollout</p>
                <p class="mt-1 text-lg font-semibold text-gray-100 tabular-nums">{{ $model->rollout_percentage }}%</p>
            </div>
            <div class="rounded-lg border border-gray-700 bg-gray-900 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Conditions</p>
                @php $conditionCount = count($model->conditions ?? []); @endphp
                <p class="mt-1 text-lg font-semibold {{ $conditionCount > 0 ? 'text-violet-400' : 'text-gray-600' }}">
                    {{ $conditionCount > 0 ? $conditionCount . ' rule' . ($conditionCount > 1 ? 's' : '') : 'None' }}
                </p>
            </div>
            <div class="rounded-lg border border-gray-700 bg-gray-900 p-4">
                <p class="text-xs text-gray-500 uppercase tracking-wide">Last Changed</p>
                <p class="mt-1 text-sm font-medium text-gray-300">{{ $model->updated_at?->diffForHumans() ?? '—' }}</p>
            </div>
        </div>

        {{-- Controls sub-component --}}
        <div>
            <h2 class="mb-4 text-lg font-semibold text-gray-100">Controls</h2>
            @livewire('ab-testing::feature-flag-controls', ['flagKey' => $model->key])
        </div>
    @endif
</div>
