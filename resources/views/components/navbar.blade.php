<div class="sticky top-0 z-30 flex h-14 items-center gap-4 border-b border-gray-800 bg-gray-950/80 backdrop-blur px-4 lg:px-6">
    {{-- Mobile hamburger (left) --}}
    <button @click="sidebarOpen = true" class="text-gray-400 hover:text-gray-200 lg:hidden">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
        </svg>
    </button>
    <span class="text-sm font-semibold text-white lg:hidden">A/B Testing</span>

    {{-- Spacer --}}
    <div class="flex-1"></div>

    {{-- ⌘K command palette trigger — $dispatch bubbles to document --}}
    <button
        @click="$dispatch('ab:open-palette')"
        class="flex items-center gap-2 rounded-md border border-gray-700 bg-gray-800/60 px-3 py-1.5 text-xs text-gray-400 hover:border-gray-600 hover:text-gray-200 transition-colors"
    >
        <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"/>
        </svg>
        <span>Search pages</span>
        <kbd class="hidden sm:inline rounded border border-gray-600 bg-gray-700 px-1 py-0.5 font-mono text-[10px] text-gray-500">⌘K</kbd>
    </button>
</div>
