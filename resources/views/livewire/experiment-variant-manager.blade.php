@use(ABTests\Enums\ExperimentStatus)

<div>
    @if($model === null)
        <p class="text-sm text-gray-500">No variant data available.</p>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

            {{-- Panel header --}}
            <div class="flex items-center justify-between border-b border-gray-700/60 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-100">Variants</h2>
                    <span class="text-xs text-gray-500">{{ $variants->count() }} {{ $variants->count() === 1 ? 'arm' : 'arms' }}</span>

                    @if($hasCodeDefinition)
                        <span class="inline-flex items-center gap-1 rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-500 border border-gray-700">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5"/>
                            </svg>
                            code definition
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-500 border border-gray-700">
                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 2.625v2.625m0 2.625v2.625M3.75 9v2.625m0 2.625v2.625"/>
                            </svg>
                            db snapshot
                        </span>
                    @endif
                </div>

                @if($isEditable)
                    <button wire:click="startAdd"
                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-600 bg-transparent px-3 py-1.5 text-xs font-medium text-gray-300 hover:border-gray-500 hover:text-gray-100 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add variant
                    </button>
                @endif
            </div>

            {{-- Live-experiment warning --}}
            @if($isEditable && $status !== null && in_array($status, [ExperimentStatus::running, ExperimentStatus::paused], true))
                <div class="flex items-start gap-2 border-b border-amber-700/50 bg-amber-900/20 px-6 py-3 text-xs text-amber-300">
                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                    <span>
                        Changes to a live experiment affect only <strong>future assignments</strong> — existing sticky assignments are never changed.
                        Mid-flight changes will cause a Sample Ratio Mismatch warning.
                    </span>
                </div>
            @endif

            {{-- Flash message --}}
            @if($flashMessage !== '')
                <div class="px-6 py-3 text-sm border-b border-gray-700/60
                    {{ $flashType === 'error'
                        ? 'bg-red-900/20 text-red-300 border-red-700/50'
                        : 'bg-green-900/20 text-green-300 border-green-700/50' }}">
                    {{ $flashMessage }}
                </div>
            @endif

            {{-- Variant rows --}}
            <div class="divide-y divide-gray-700/40">
                @forelse($variants as $variant)
                    @if($isEditable && $editingId === $variant->id)
                        {{-- ── Inline edit row ── --}}
                        <div class="px-6 py-4 bg-gray-800/40">
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">

                                {{-- Key --}}
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Key</label>
                                    <input
                                        type="text"
                                        wire:model="editKey"
                                        class="block w-full rounded-md border border-gray-600 bg-gray-900 px-3 py-2 font-mono text-sm text-gray-100
                                               placeholder-gray-600 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                                        placeholder="variant-key"
                                    >
                                </div>

                                {{-- Weight --}}
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Weight (%)</label>
                                    <input
                                        type="number"
                                        min="1"
                                        max="99"
                                        wire:model="editWeight"
                                        class="block w-24 rounded-md border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100 tabular-nums
                                               focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                                    >
                                </div>

                                {{-- Control toggle --}}
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Control</label>
                                    <div class="flex h-[38px] items-center">
                                        <input
                                            type="checkbox"
                                            id="edit-is-control-{{ $variant->id }}"
                                            wire:model="editIsControl"
                                            {{ $variant->is_control ? 'disabled' : '' }}
                                            class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-violet-500 focus:ring-violet-500 focus:ring-offset-gray-900"
                                        >
                                    </div>
                                </div>

                                {{-- Actions --}}
                                <div class="flex items-end gap-2">
                                    <button wire:click="saveEdit"
                                            class="inline-flex items-center rounded-md bg-violet-700 px-3 py-2 text-sm font-medium text-violet-100 hover:bg-violet-600 transition-colors">
                                        Save
                                    </button>
                                    <button wire:click="cancelEdit"
                                            class="inline-flex items-center rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-400 hover:text-gray-200 transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    @else
                        {{-- ── Display row ── --}}
                        <div class="flex items-center gap-4 px-6 py-4 {{ $isEditable ? 'group hover:bg-gray-800/20 transition-colors' : '' }}">

                            {{-- Dot + key + control badge --}}
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span class="h-2 w-2 shrink-0 rounded-full {{ $variant->is_control ? 'bg-gray-400' : 'bg-violet-500' }}"></span>
                                <span class="font-mono text-sm text-gray-100">{{ $variant->key }}</span>
                                @if($variant->is_control)
                                    <span class="inline-flex items-center rounded bg-gray-700/80 px-1.5 py-0.5 text-xs text-gray-400">control</span>
                                @endif
                            </div>

                            {{-- Allocation bar --}}
                            <div class="hidden sm:flex flex-1 max-w-xs items-center gap-2">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-gray-700">
                                    <div class="h-full rounded-full {{ $variant->is_control ? 'bg-gray-500' : 'bg-violet-600' }} transition-all duration-300"
                                         style="width: {{ $variant->weight }}%"></div>
                                </div>
                            </div>

                            {{-- Weight --}}
                            <span class="w-12 shrink-0 text-right text-sm tabular-nums font-medium {{ $variant->is_control ? 'text-gray-400' : 'text-violet-300' }}">
                                {{ $variant->weight }}%
                            </span>

                            {{-- Edit / remove (only when editable) --}}
                            @if($isEditable)
                                <div class="flex shrink-0 items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="startEdit({{ $variant->id }})"
                                            title="Edit"
                                            class="rounded p-1 text-gray-500 hover:text-gray-200 transition-colors">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                                        </svg>
                                    </button>
                                    @if(!$variant->is_control)
                                        <button
                                            wire:click="removeVariant({{ $variant->id }})"
                                            wire:confirm="Remove variant '{{ $variant->key }}'?"
                                            title="Remove"
                                            class="rounded p-1 text-gray-500 hover:text-red-400 transition-colors">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endif
                @empty
                    <div class="px-6 py-6 text-center text-sm text-gray-500">
                        No variants defined yet.
                    </div>
                @endforelse
            </div>

            {{-- Add variant form --}}
            @if($isEditable && $showAddForm)
                <div class="border-t border-gray-700/60 px-6 py-4 bg-gray-800/30">
                    <p class="mb-3 text-xs font-medium text-gray-400 uppercase tracking-wide">New variant</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">

                        {{-- Key --}}
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Key</label>
                            <input
                                type="text"
                                wire:model="newKey"
                                class="block w-full rounded-md border border-gray-600 bg-gray-900 px-3 py-2 font-mono text-sm text-gray-100
                                       placeholder-gray-600 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                                placeholder="treatment-name"
                                autofocus
                            >
                        </div>

                        {{-- Weight --}}
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Weight (%)</label>
                            <input
                                type="number"
                                min="1"
                                max="99"
                                wire:model="newWeight"
                                class="block w-24 rounded-md border border-gray-600 bg-gray-900 px-3 py-2 text-sm text-gray-100 tabular-nums
                                       focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                            >
                        </div>

                        {{-- Control toggle --}}
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Control</label>
                            <div class="flex h-[38px] items-center">
                                <input
                                    type="checkbox"
                                    id="new-is-control"
                                    wire:model="newIsControl"
                                    class="h-4 w-4 rounded border-gray-600 bg-gray-900 text-violet-500 focus:ring-violet-500 focus:ring-offset-gray-900"
                                >
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-end gap-2">
                            <button wire:click="saveAdd"
                                    class="inline-flex items-center rounded-md bg-violet-700 px-3 py-2 text-sm font-medium text-violet-100 hover:bg-violet-600 transition-colors">
                                Add
                            </button>
                            <button wire:click="cancelAdd"
                                    class="inline-flex items-center rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-400 hover:text-gray-200 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Weight total footer (editable only) --}}
            @if($isEditable)
                <div class="border-t border-gray-700/60 px-6 py-3 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Total allocation</span>
                    <span class="text-sm font-semibold tabular-nums {{ $totalWeight === 100 ? 'text-green-400' : 'text-amber-400' }}">
                        {{ $totalWeight }}%
                        @if($totalWeight !== 100)
                            <span class="ml-1 font-normal text-xs text-amber-500/80">(must equal 100%)</span>
                        @endif
                    </span>
                </div>
            @endif
        </div>

        {{-- Read-only notice --}}
        @if($hasCodeDefinition)
            <p class="mt-2 text-xs text-gray-600">
                Variants are defined in code and cannot be edited from the dashboard.
            </p>
        @elseif($status !== null && in_array($status, [ExperimentStatus::completed, ExperimentStatus::archived], true))
            <p class="mt-2 text-xs text-gray-600">
                Variants are locked on completed and archived experiments.
            </p>
        @endif
    @endif
</div>
