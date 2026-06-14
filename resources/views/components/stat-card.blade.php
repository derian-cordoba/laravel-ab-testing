{{--
    Reusable stat card.

    Props:
        $label  — required  — small uppercase label
        $value  — required  — large primary number / value
        $sub    — optional  — small muted footer line
--}}
@props([
    'label' => '',
    'value' => '',
    'sub'   => null,
])

<div class="rounded-lg border border-gray-700 bg-gray-900 p-5">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ $label }}</p>
    <p class="mt-2 text-3xl font-semibold tabular-nums text-white">{{ $value }}</p>
    @if($sub || (isset($slot) && $slot->isNotEmpty()))
        <div class="mt-1 text-xs text-gray-500">
            @if(isset($slot) && $slot->isNotEmpty())
                {{ $slot }}
            @else
                {{ $sub }}
            @endif
        </div>
    @endif
</div>
