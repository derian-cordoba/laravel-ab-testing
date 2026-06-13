@use(ABTests\Enums\ExperimentStatus)

<div x-data="{ open: true }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Controls</h2>
            <span class="text-xs text-gray-500">lifecycle &amp; traffic management</span>
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

        @if($model === null)
            <div class="p-6">
                <p class="text-sm text-gray-500">Experiment record not found.</p>
            </div>
        @else
            @php
                $canStart   = in_array(ExperimentStatus::running, $allowedTransitions, true);
                $canPause   = in_array(ExperimentStatus::paused, $allowedTransitions, true);
                $canResume  = in_array(ExperimentStatus::running, $allowedTransitions, true) && $status === ExperimentStatus::paused;
                $canStop    = in_array(ExperimentStatus::completed, $allowedTransitions, true);
                $canArchive = in_array(ExperimentStatus::archived, $allowedTransitions, true);
                $isRunning  = $status === ExperimentStatus::running;
            @endphp

            <div class="px-6 py-5 space-y-5">
                <x-ab-testing::flash-message :message="$this->flashMessage" :type="$this->flashType" />

                {{-- Lifecycle actions --}}
                @if($status !== ExperimentStatus::archived)
                    <div>
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Lifecycle</p>
                        <div class="flex flex-wrap gap-2">
                            @if($canStart && $status !== ExperimentStatus::paused)
                                <button wire:click="start"
                                        wire:confirm="Start this experiment? Traffic will begin flowing to variants."
                                        class="inline-flex items-center rounded-md bg-green-700 px-3 py-2 text-sm font-medium text-green-100 hover:bg-green-600 transition-colors">
                                    Start
                                </button>
                            @endif

                            @if($canPause && !($status === ExperimentStatus::paused))
                                <button wire:click="pause"
                                        wire:confirm="Pause this experiment? Assignment will stop."
                                        class="inline-flex items-center rounded-md bg-yellow-700 px-3 py-2 text-sm font-medium text-yellow-100 hover:bg-yellow-600 transition-colors">
                                    Pause
                                </button>
                            @endif

                            @if($canResume)
                                <button wire:click="resume"
                                        wire:confirm="Resume this experiment?"
                                        class="inline-flex items-center rounded-md bg-blue-700 px-3 py-2 text-sm font-medium text-blue-100 hover:bg-blue-600 transition-colors">
                                    Resume
                                </button>
                            @endif

                            @if($canStop)
                                <button wire:click="stop"
                                        wire:confirm="Stop this experiment permanently? This cannot be undone."
                                        class="inline-flex items-center rounded-md bg-gray-700 px-3 py-2 text-sm font-medium text-gray-100 hover:bg-gray-600 transition-colors">
                                    Stop
                                </button>
                            @endif

                            @if($canArchive)
                                <button wire:click="archive"
                                        wire:confirm="Archive this experiment? It will be read-only."
                                        class="inline-flex items-center rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-400 hover:border-gray-500 hover:text-gray-200 transition-colors">
                                    Archive
                                </button>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Kill switch --}}
                @if($showKillSwitch && ($isRunning || $model->is_killed))
                    <div class="border-t border-gray-700/60 pt-5">
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Kill Switch</p>
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm text-gray-400">
                                @if($model->is_killed)
                                    Kill switch is <strong class="text-red-400">active</strong>. All traffic is being served the control variant.
                                @else
                                    Instantly serves the control variant to all units without changing assignment records.
                                @endif
                            </p>
                            <button wire:click="toggleKillSwitch"
                                    wire:confirm="{{ $model->is_killed ? 'Deactivate the kill switch?' : 'Activate the kill switch? All traffic will be forced to control.' }}"
                                    class="shrink-0 inline-flex items-center rounded-md px-3 py-2 text-sm font-medium border transition-colors
                                        {{ $model->is_killed
                                            ? 'border-green-700 bg-green-900/30 text-green-300 hover:bg-green-900/60'
                                            : 'border-red-700 bg-red-900/30 text-red-300 hover:bg-red-900/60' }}">
                                {{ $model->is_killed ? 'Deactivate' : 'Activate' }}
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Refresh rollup --}}
                @if($showData)
                    <div class="border-t border-gray-700/60 pt-5">
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Data</p>
                        <div class="flex items-center justify-between gap-4">
                            <p class="text-sm text-gray-400">Recompute rollups from raw events now, without waiting for the scheduler.</p>
                            <button wire:click="refreshRollup"
                                    wire:loading.attr="disabled"
                                    class="shrink-0 inline-flex items-center gap-1.5 rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-300 hover:border-gray-500 hover:text-gray-100 transition-colors">
                                <svg wire:loading.remove wire:target="refreshRollup" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                                </svg>
                                <svg wire:loading wire:target="refreshRollup" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                                Refresh Data
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Traffic ramp --}}
                @if($showTrafficRamp && $isRunning)
                    <div class="border-t border-gray-700/60 pt-5">
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Traffic Ramp</p>
                        <div class="flex items-end gap-3">
                            <div class="flex-1 max-w-xs">
                                <label for="trafficPercentage" class="block text-xs text-gray-500 mb-1.5">
                                    Percentage of eligible units assigned (0–100)
                                </label>
                                <input
                                    id="trafficPercentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    wire:model="trafficPercentage"
                                    class="block w-full rounded-md border border-gray-600 bg-gray-800 text-sm text-gray-100 placeholder-gray-500
                                           focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none px-3 py-2"
                                >
                                @error('trafficPercentage')
                                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            <button wire:click="rampTraffic"
                                    class="inline-flex items-center rounded-md bg-violet-700 px-4 py-2 text-sm font-medium text-violet-100 hover:bg-violet-600 transition-colors">
                                Apply
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">
                            Current traffic: <strong class="text-gray-300">{{ $model->traffic_percentage }}%</strong>
                        </p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
