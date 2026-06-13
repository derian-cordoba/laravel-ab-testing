{{-- Power Analysis panel — self-contained accordion --}}
<div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Power Analysis</h2>
            <span class="text-xs text-gray-500">sample size calculator</span>
        </div>
        <div class="flex items-center gap-2">
            @if($currentTargetSampleSize)
                <span class="text-xs text-gray-500">
                    Target: <strong class="text-gray-300 tabular-nums">{{ number_format($currentTargetSampleSize) }}</strong>
                </span>
            @endif
            <svg :class="open ? 'rotate-180' : ''"
                 class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
            </svg>
        </div>
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">

        <div class="px-6 py-5 space-y-5">
            @if($flashMessage)
                <div class="rounded-md px-4 py-2 text-sm
                    {{ $flashType === 'success' ? 'bg-green-900/30 text-green-300' : 'bg-red-900/30 text-red-300' }}">
                    {{ $flashMessage }}
                </div>
            @endif

            {{-- Input form --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Baseline rate / mean</label>
                    <input
                        type="number" step="0.001" min="0.001" max="0.999"
                        wire:model="baselineRate"
                        placeholder="e.g. 0.12 for 12%"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                    >
                    @error('baselineRate')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Min. detectable effect</label>
                    <input
                        type="number" step="0.001" min="0.001"
                        wire:model="minimumDetectableEffect"
                        placeholder="e.g. 0.05 for 5%"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                    >
                    @error('minimumDetectableEffect')
                        <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Confidence level</label>
                    <select
                        wire:model="confidenceLevel"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                    >
                        <option value="0.90">90%</option>
                        <option value="0.95">95%</option>
                        <option value="0.99">99%</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Statistical power</label>
                    <select
                        wire:model="statisticalPower"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                    >
                        <option value="0.80">80%</option>
                        <option value="0.85">85%</option>
                        <option value="0.90">90%</option>
                    </select>
                </div>

                <div class="col-span-2 flex items-center gap-2">
                    <input
                        type="checkbox" id="isRelativeEffect"
                        wire:model="isRelativeEffect"
                        class="rounded border-gray-600 bg-gray-800 text-violet-600 focus:ring-violet-500"
                    >
                    <label for="isRelativeEffect" class="text-xs text-gray-400">
                        Interpret MDE as a relative effect (% of baseline)
                    </label>
                </div>

                @if(!$isBinaryMetric)
                <div class="col-span-2">
                    <label class="block text-xs font-medium text-gray-400 mb-1.5">Standard deviation (continuous metrics)</label>
                    <input
                        type="number" step="0.01" min="0.01"
                        wire:model="standardDeviation"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                    >
                </div>
                @endif
            </div>

            <div>
                <button
                    wire:click="calculate"
                    class="inline-flex items-center rounded-md bg-violet-700 px-4 py-2 text-sm font-medium text-violet-100 hover:bg-violet-600 transition-colors"
                >
                    Calculate
                </button>
            </div>

            {{-- Result --}}
            @if($result)
                <div class="rounded-lg border border-gray-700/60 bg-gray-800/40 p-5 space-y-4">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Per variant</p>
                            <p class="mt-1 text-2xl font-bold text-gray-100 tabular-nums">{{ number_format($result['sampleSizePerVariant']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total ({{ $result['numberOfVariants'] }} arms)</p>
                            <p class="mt-1 text-2xl font-bold text-gray-100 tabular-nums">{{ number_format($result['totalSampleSize']) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">MDE</p>
                            <p class="mt-1 text-2xl font-bold text-gray-100 tabular-nums">
                                {{ $result['isRelativeEffect']
                                    ? number_format($result['minimumDetectableEffect'] * 100, 1) . '%'
                                    : number_format($result['minimumDetectableEffect'], 4) }}
                            </p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 tabular-nums">
                        Confidence {{ number_format($result['confidenceLevel'] * 100) }}%
                        · Power {{ number_format($result['power'] * 100) }}%
                        · Baseline {{ number_format($result['baselineRate'] * 100, 2) }}%
                    </p>

                    <button
                        wire:click="saveTargetSampleSize"
                        class="inline-flex items-center rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-300 hover:border-gray-500 hover:text-gray-100 transition-colors"
                    >
                        Set as target sample size
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
