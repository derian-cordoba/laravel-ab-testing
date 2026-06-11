@use(ABTests\Enums\ExperimentStatus)

<div x-data="{ confirmAction: null }">
    <x-ab-testing::flash-message :message="$this->flashMessage" :type="$this->flashType" />

    @if($model === null)
        <p class="text-sm text-gray-500">Experiment record not found.</p>
    @else
        @php
            $canStart   = in_array(ExperimentStatus::running, $allowedTransitions, true);
            $canPause   = in_array(ExperimentStatus::paused, $allowedTransitions, true);
            $canResume  = in_array(ExperimentStatus::running, $allowedTransitions, true) && $status === ExperimentStatus::paused;
            $canStop    = in_array(ExperimentStatus::completed, $allowedTransitions, true);
            $canArchive = in_array(ExperimentStatus::archived, $allowedTransitions, true);
            $isRunning  = $status === ExperimentStatus::running;
        @endphp

        <div class="rounded-lg border border-gray-700 bg-gray-900 p-6 space-y-6">

            {{-- Lifecycle actions --}}
            <div>
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Lifecycle</h3>
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

            {{-- Kill switch --}}
            @if($isRunning || $model->is_killed)
                <div class="border-t border-gray-700/60 pt-5">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">Kill Switch</h3>
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
            <div class="border-t border-gray-700/60 pt-5">
                <h3 class="text-sm font-semibold text-gray-300 mb-3">Data</h3>
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

            {{-- Traffic ramp --}}
            @if($isRunning)
                <div class="border-t border-gray-700/60 pt-5">
                    <h3 class="text-sm font-semibold text-gray-300 mb-3">Traffic Ramp</h3>
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
