{{--
    Reusable accordion component.

    Props:
        $title   — required  — bold heading text
        $sub     — optional  — muted subtitle beside the heading
        $open    — optional  — Alpine expression for initial open state (default false)

    Slots:
        $badge   — optional  — rendered between the subtitle and the chevron (e.g. status pill)
        $slot    — required  — accordion body content
--}}
@props([
    'title'  => '',
    'sub'    => null,
    'open'   => 'false',
    'badge'  => null,
])

<div x-data="{ open: {{ $open }} }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">

        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">{{ $title }}</h2>
            @if($sub)
                <span class="text-xs text-gray-500">{{ $sub }}</span>
            @endif
        </div>

        <div class="flex items-center gap-2">
            @if(isset($badge))
                {{ $badge }}
            @endif
            <svg :class="open ? 'rotate-180' : ''"
                 class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
                 fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
            </svg>
        </div>
    </div>

    {{-- Body --}}
    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">
        {{ $slot }}
    </div>
</div>
