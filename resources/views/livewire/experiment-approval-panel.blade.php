@use(ABTests\Enums\ApprovalStatus)

{{-- Approval Panel — only visible when governance.approval_required is true --}}
<div>
@if($approvalRequired)
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Approval</h3>
            @if($latestApproval)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $latestApproval->status->badgeClass() }}">
                {{ $latestApproval->status->label() }}
            </span>
            @else
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                No Request
            </span>
            @endif
        </div>

        <div class="px-6 py-4 space-y-4">
            @if($flashMessage)
                <div class="rounded-md px-4 py-2 text-sm {{ $flashType === 'success' ? 'bg-green-50 text-green-800' : 'bg-red-50 text-red-800' }}">
                    {{ $flashMessage }}
                </div>
            @endif

            {{-- History --}}
            @if($approvalHistory->isNotEmpty())
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">History</p>
                    <ul class="space-y-2">
                        @foreach($approvalHistory as $entry)
                            <li class="flex items-start gap-3 text-sm">
                    <span class="mt-0.5 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $entry->status->badgeClass() }}">
                        {{ $entry->status->label() }}
                    </span>
                                <div class="flex-1 min-w-0">
                                    <span class="text-gray-700">{{ $entry->reviewed_by ?? $entry->requested_by }}</span>
                                    @if($entry->notes)
                                        <p class="text-gray-500 truncate">{{ $entry->notes }}</p>
                                    @endif
                                    <p class="text-gray-400 text-xs">{{ $entry->created_at->diffForHumans() }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Action form --}}
            <div class="space-y-3 pt-2 border-t border-gray-100">
            <textarea
                    wire:model="notes"
                    rows="2"
                    placeholder="Optional notes…"
                    class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500"
            ></textarea>

                <div class="flex flex-wrap gap-2">
                    @if(!$latestApproval || $latestApproval->status === ApprovalStatus::rejected)
                        <button
                                wire:click="requestApproval"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            Request Approval
                        </button>
                    @endif

                    @if($latestApproval && $latestApproval->status === ApprovalStatus::pending)
                        <button
                                wire:click="approve"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-green-600 text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            Approve
                        </button>
                        <button
                                wire:click="reject"
                                class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-red-600 text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500"
                        >
                            Reject
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
</div>
