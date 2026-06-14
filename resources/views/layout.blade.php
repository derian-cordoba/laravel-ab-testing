<!doctype html>
<html lang="en" class="h-full bg-gray-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'A/B Testing Dashboard' }}</title>

    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body class="h-full bg-gray-950 text-gray-100">

{{-- ── Keyboard shortcut: ⌘K / Ctrl+K opens the command palette ──── --}}
{{-- Uses a plain CustomEvent so we never need to access window.Alpine    --}}
{{-- (Livewire 4 bundles Alpine and does not expose it as a global).      --}}
<script>
    document.addEventListener('keydown', function (e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            document.dispatchEvent(new CustomEvent('ab:open-palette'));
        }
    });
</script>

<div class="flex min-h-screen" x-data="{ sidebarOpen: false }">

    {{-- Mobile sidebar overlay --}}
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-40 bg-black/60 lg:hidden"
         @click="sidebarOpen = false">
    </div>

    @livewire('ab-testing::dashboard-sidebar')

    {{-- Main column --}}
    <div class="flex flex-1 flex-col min-w-0 lg:pl-0">

        <x-ab-testing::navbar />

        <main class="flex-1 px-6 py-8 lg:px-10">
            {{ $slot }}
        </main>
    </div>
</div>

{{-- ── Command palette overlay ──────────────────────────────────────────── --}}
{{-- State lives entirely in this component's x-data; opened via a plain   --}}
{{-- CustomEvent so we never need window.Alpine (Livewire 4 doesn't expose --}}
{{-- it as a global). The navbar button uses Alpine's built-in $dispatch.  --}}
@php
    $abPaletteRoutes = json_encode([
        ['group' => 'Dashboard',   'label' => 'Overview',        'url' => route('ab-testing.index')],
        ['group' => 'Experiments', 'label' => 'Experiments',     'url' => route('ab-testing.experiments.index')],
        ['group' => 'Experiments', 'label' => 'New experiment',  'url' => route('ab-testing.experiments.create')],
        ['group' => 'Experiments', 'label' => 'Layers map',      'url' => route('ab-testing.layers')],
        ['group' => 'Experiments', 'label' => 'Metrics catalog', 'url' => route('ab-testing.metrics')],
        ['group' => 'Experiments', 'label' => 'Segments',        'url' => route('ab-testing.segments')],
        ['group' => 'Flags',       'label' => 'Feature flags',   'url' => route('ab-testing.feature-flags.index')],
        ['group' => 'Flags',       'label' => 'New flag',        'url' => route('ab-testing.feature-flags.create')],
        ['group' => 'Governance',  'label' => 'Audit log',       'url' => route('ab-testing.audit-log')],
        ['group' => 'Governance',  'label' => 'QA overrides',    'url' => route('ab-testing.qa-overrides')],
    ]);
@endphp
<script>window._abRoutes = {!! $abPaletteRoutes !!};</script>

<div x-data="{
        open:   false,
        query:  '',
        routes: window._abRoutes,
        get filtered() {
            var q = this.query.toLowerCase().trim();
            if (!q) return this.routes;
            return this.routes.filter(function(r) {
                return r.label.toLowerCase().includes(q) || r.group.toLowerCase().includes(q);
            });
        },
        show() { this.open = true; this.query = ''; this.$nextTick(function() { document.getElementById('ab-palette-input') && document.getElementById('ab-palette-input').focus(); }); },
        hide() { this.open = false; },
    }"
     @ab:open-palette.document="show()"
     @keydown.escape.window="hide()"
     x-show="open"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-100"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click.self="hide()"
     class="fixed inset-0 z-[100] flex items-start justify-center pt-[15vh] px-4"
     style="display:none; background: rgba(0,0,0,0.55); backdrop-filter: blur(2px);">

    <div @click.stop
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="w-full max-w-lg overflow-hidden rounded-xl border border-gray-700 bg-gray-900 shadow-2xl">

        {{-- Search input --}}
        <div class="flex items-center gap-3 border-b border-gray-700/60 px-4 py-3">
            <svg class="h-4 w-4 shrink-0 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 15.803a7.5 7.5 0 0 0 10.607 0Z"/>
            </svg>
            <input
                id="ab-palette-input"
                x-model="query"
                type="text"
                placeholder="Search pages…"
                autocomplete="off"
                class="flex-1 bg-transparent text-sm text-gray-100 placeholder-gray-500 focus:outline-none"
            >
        </div>

        {{-- Results --}}
        <div class="max-h-80 overflow-y-auto py-2">
            <template x-if="filtered.length === 0">
                <p class="px-4 py-6 text-center text-sm text-gray-500">No pages match.</p>
            </template>
            <template x-for="(r, i) in filtered" :key="i">
                <a :href="r.url"
                   @click="hide()"
                   class="flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-gray-800/60 transition-colors">
                    <span class="text-xs text-gray-600 w-24 shrink-0 truncate" x-text="r.group"></span>
                    <span class="text-gray-200 flex-1" x-text="r.label"></span>
                    <svg class="h-3.5 w-3.5 shrink-0 text-gray-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
            </template>
        </div>
    </div>
</div>

@livewireScripts

</body>
</html>
