@use(ABTests\Enums\Operator)

<div x-data="{ open: true }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Controls</h2>
            <span class="text-xs text-gray-500">toggle, rollout &amp; targeting</span>
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

        <div class="px-6 py-5 space-y-5">
            <x-ab-testing::flash-message :message="$this->flashMessage" :type="$this->flashType" />

            {{-- Toggle + Kill switch — two columns --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                {{-- Enable / Disable --}}
                <div class="rounded-lg border border-gray-700/60 bg-gray-800/30 px-4 py-4">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Toggle</p>
                    <div class="flex flex-wrap gap-2">
                        @if($model === null || !$model->is_enabled)
                            <button wire:click="enable"
                                    class="inline-flex items-center rounded-md bg-green-700 px-3 py-2 text-sm font-medium text-green-100 hover:bg-green-600 transition-colors">
                                Enable
                            </button>
                        @endif

                        @if($model !== null && $model->is_enabled)
                            <button wire:click="disable"
                                    class="inline-flex items-center rounded-md bg-gray-700 px-3 py-2 text-sm font-medium text-gray-100 hover:bg-gray-600 transition-colors">
                                Disable
                            </button>
                        @endif
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        @if($model !== null && $model->is_enabled)
                            Flag is currently <strong class="text-green-400">enabled</strong> and resolving for eligible units.
                        @else
                            Flag is currently <strong class="text-gray-400">disabled</strong> and will not resolve for any unit.
                        @endif
                    </p>
                </div>

                {{-- Kill switch --}}
                @if($showKillSwitch)
                    <div class="rounded-lg border border-gray-700/60 bg-gray-800/30 px-4 py-4">
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Kill Switch</p>
                        <button wire:click="toggleKillSwitch"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium border transition-colors
                                    {{ ($model !== null && $model->killed_at !== null)
                                        ? 'border-green-700 bg-green-900/30 text-green-300 hover:bg-green-900/60'
                                        : 'border-red-700 bg-red-900/30 text-red-300 hover:bg-red-900/60' }}">
                            {{ ($model !== null && $model->killed_at !== null) ? 'Deactivate' : 'Activate' }}
                        </button>
                        <p class="mt-3 text-xs text-gray-500">
                            @if($model !== null && $model->killed_at !== null)
                                Kill switch is <strong class="text-red-400">active</strong>. Flag resolves to its default value for all units.
                            @else
                                Instantly forces the flag to its default value for all units without changing other settings.
                            @endif
                        </p>
                    </div>
                @endif
            </div>

            {{-- Rollout percentage --}}
            @if($showRolloutPercentage)
                <div class="border-t border-gray-700/60 pt-5 mt-1">
                    <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">Rollout Percentage</p>
                    <div class="flex items-end gap-3">
                        <div class="flex-1 max-w-xs">
                            <label for="rolloutPercentage" class="block text-xs text-gray-500 mb-1.5">
                                Percentage of eligible units that receive this flag (0–100)
                            </label>
                            <input
                                id="rolloutPercentage"
                                type="number"
                                min="0"
                                max="100"
                                wire:model="rolloutPercentage"
                                class="block w-full rounded-md border border-gray-600 bg-gray-800 text-sm text-gray-100 placeholder-gray-500
                                       focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none px-3 py-2"
                            >
                            @error('rolloutPercentage')
                                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <button wire:click="setRollout"
                                class="inline-flex items-center rounded-md bg-violet-700 px-4 py-2 text-sm font-medium text-violet-100 hover:bg-violet-600 transition-colors">
                            Apply
                        </button>
                    </div>
                    @if($model !== null)
                        <p class="mt-2 text-xs text-gray-500">
                            Current rollout: <strong class="text-gray-300">{{ $model->rollout_percentage }}%</strong>
                        </p>
                    @endif
                </div>
            @endif

            {{-- Targeting conditions --}}
            <div class="border-t border-gray-700/60 pt-5">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Targeting Conditions</p>
                    @if(count($this->conditions) > 0)
                        <span class="text-xs text-gray-500">All conditions must match (AND)</span>
                    @endif
                </div>
                <p class="mb-4 text-xs text-gray-500">
                    Restrict this flag to units whose attributes satisfy every condition.
                    Leave empty to target all eligible units.
                </p>

                {{-- Current conditions list --}}
                @if(count($this->conditions) > 0)
                    <ul class="mb-4 space-y-2">
                        @foreach($this->conditions as $index => $condition)
                            <li class="flex items-center gap-2 rounded-md border border-gray-700 bg-gray-800/60 px-3 py-2 text-sm">
                                <code class="text-violet-300">{{ $condition['attribute'] }}</code>
                                <span class="text-gray-500">{{ (Operator::tryFrom($condition['operator']))?->label() ?? $condition['operator'] }}</span>
                                <code class="text-green-300">
                                    {{ is_array($condition['expected'])
                                        ? implode(', ', $condition['expected'])
                                        : $condition['expected'] }}
                                </code>
                                <button wire:click="removeCondition({{ $index }})"
                                        class="ml-auto text-gray-600 hover:text-red-400 transition-colors"
                                        title="Remove condition">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mb-4 text-xs text-gray-600 italic">No conditions — flag applies to all eligible units.</p>
                @endif

                {{-- Add condition form --}}
                <div class="rounded-md border border-gray-700/60 bg-gray-800/30 p-4">
                    <p class="mb-3 text-xs font-medium text-gray-400">Add condition</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Attribute</label>
                            <input
                                type="text"
                                wire:model="newAttribute"
                                placeholder="e.g. plan"
                                class="w-32 rounded-md border border-gray-600 bg-gray-800 px-2.5 py-1.5 text-sm text-gray-100 placeholder-gray-600
                                       focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                            >
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Operator</label>
                            <select
                                wire:model="newOperator"
                                class="rounded-md border border-gray-600 bg-gray-800 px-2.5 py-1.5 text-sm text-gray-100
                                       focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                            >
                                @foreach(Operator::cases() as $op)
                                    <option value="{{ $op->value }}">{{ $op->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">
                                Value
                                @if(in_array($this->newOperator, ['in', 'not_in'], true))
                                    <span class="text-gray-600">(comma-separated)</span>
                                @endif
                            </label>
                            <input
                                type="text"
                                wire:model="newValue"
                                placeholder="e.g. pro"
                                class="w-40 rounded-md border border-gray-600 bg-gray-800 px-2.5 py-1.5 text-sm text-gray-100 placeholder-gray-600
                                       focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                            >
                        </div>
                        <button wire:click="addCondition"
                                class="inline-flex items-center gap-1 rounded-md border border-gray-600 bg-gray-700 px-3 py-1.5 text-sm font-medium text-gray-200 hover:bg-gray-600 transition-colors">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            Add
                        </button>
                    </div>
                </div>

                {{-- Save conditions --}}
                <div class="mt-3 flex items-center gap-3">
                    <button wire:click="saveConditions"
                            class="inline-flex items-center rounded-md bg-violet-700 px-4 py-2 text-sm font-medium text-violet-100 hover:bg-violet-600 transition-colors">
                        Save Conditions
                    </button>
                    <p class="text-xs text-gray-600">Changes to the list above are not persisted until you save.</p>
                </div>
            </div>
        </div>
    </div>
</div>
