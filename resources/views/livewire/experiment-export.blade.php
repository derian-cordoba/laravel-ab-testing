{{-- Export panel — embedded in experiment-detail --}}
<div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
    <div class="flex items-center justify-between border-b border-gray-700/60 px-6 py-4">
        <div>
            <h2 class="font-semibold text-gray-100">Export</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ number_format($eventCount) }} raw event{{ $eventCount !== 1 ? 's' : '' }} available for download.
            </p>
        </div>
    </div>

    <div class="px-6 py-5 space-y-4">
        @if($flashMessage)
            <div class="rounded-md px-4 py-2 text-sm
                {{ $flashType === 'success' ? 'bg-green-900/30 text-green-300' : 'bg-red-900/30 text-red-300' }}">
                {{ $flashMessage }}
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <button
                wire:click="downloadJson"
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-300 hover:border-gray-500 hover:text-gray-100 transition-colors"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Rollup JSON
            </button>

            <button
                wire:click="downloadCsv"
                @disabled($eventCount === 0)
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-600 bg-transparent px-3 py-2 text-sm font-medium text-gray-300 hover:border-gray-500 hover:text-gray-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            >
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Raw Events CSV
            </button>
        </div>

        <p class="text-xs text-gray-500">
            JSON contains pre-aggregated sufficient statistics. CSV contains the full raw event stream.
        </p>
    </div>
</div>
