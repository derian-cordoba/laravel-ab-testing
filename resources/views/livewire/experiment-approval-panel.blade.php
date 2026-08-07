@use(ABTests\Enums\ApprovalStatus)
@use(ABTests\Presentation\Support\ApprovalStatusPresenter)

{{-- Approval panel — only visible when governance.approval_required is true --}}
<div>
@if($approvalRequired)
    <div x-data="{ open: true }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

        {{-- Accordion header --}}
        <div @click="open = !open"
             role="button" tabindex="0" @keydown.enter="open = !open"
             class="flex items-center justify-between min-h-14 px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
             :class="open ? 'border-b border-gray-700/60' : ''">
            <div class="flex items-center gap-3">
                <h2 class="font-semibold text-gray-100">Approval</h2>
                <span class="text-xs text-gray-500">governance workflow</span>
            </div>
            <div class="flex items-center gap-2">
                @if($latestApproval)
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ ApprovalStatusPresenter::badgeClass($latestApproval->status) }}">
                        {{ $latestApproval->status->label() }}
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-700/60 px-2.5 py-0.5 text-xs font-medium text-gray-400">
                        No request
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

                {{-- History timeline --}}
                @if($approvalHistory->isNotEmpty())
                    <div>
                        <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500">History</p>
                        <ul class="space-y-3">
                            @foreach($approvalHistory as $entry)
                                <li class="flex items-start gap-3 text-sm">
                                    <span class="mt-0.5 inline-flex shrink-0 items-center rounded px-1.5 py-0.5 text-xs font-medium {{ ApprovalStatusPresenter::badgeClass($entry->status) }}">
                                        {{ $entry->status->label() }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-gray-300 text-sm">{{ $entry->reviewed_by ?? $entry->requested_by }}</p>
                                        @if($entry->notes)
                                            <p class="text-gray-500 text-xs truncate mt-0.5">{{ $entry->notes }}</p>
                                        @endif
                                        <p class="text-gray-600 text-xs mt-0.5">{{ $entry->created_at->diffForHumans() }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Action form --}}
                <div class="space-y-3 border-t border-gray-700/60 pt-4">
                    <textarea
                        wire:model="notes"
                        rows="2"
                        placeholder="Optional notes…"
                        class="block w-full rounded-md border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100 placeholder-gray-500 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 focus:outline-none"
                    ></textarea>

                    <div class="flex flex-wrap gap-2">
                        @if(!$latestApproval || $latestApproval->status === ApprovalStatus::rejected)
                            <button
                                wire:click="requestApproval"
                                class="inline-flex items-center rounded-md bg-violet-700 px-3 py-1.5 text-xs font-medium text-violet-100 hover:bg-violet-600 transition-colors"
                            >
                                Request Approval
                            </button>
                        @endif

                        @if($latestApproval && $latestApproval->status === ApprovalStatus::pending)
                            <button
                                wire:click="approve"
                                class="inline-flex items-center rounded-md bg-green-700 px-3 py-1.5 text-xs font-medium text-green-100 hover:bg-green-600 transition-colors"
                            >
                                Approve
                            </button>
                            <button
                                wire:click="reject"
                                class="inline-flex items-center rounded-md bg-red-700 px-3 py-1.5 text-xs font-medium text-red-100 hover:bg-red-600 transition-colors"
                            >
                                Reject
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
</div>
