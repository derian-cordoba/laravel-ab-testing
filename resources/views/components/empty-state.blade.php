{{--
    Reusable empty state.

    Props:
        $message  — required  — primary message text
        $hint     — optional  — secondary smaller hint text
        $dashed   — optional  — use dashed border (default false)
--}}
@props([
    'message' => '',
    'hint'    => null,
    'dashed'  => false,
])

<div class="rounded-lg border {{ $dashed ? 'border-dashed border-gray-700' : 'border-gray-700 bg-gray-800/50' }} p-10 text-center">
    <p class="text-sm text-gray-400">{{ $message }}</p>
    @if($hint)
        <p class="mt-1 text-xs text-gray-600">{{ $hint }}</p>
    @elseif(isset($slot) && $slot->isNotEmpty())
        <div class="mt-1 text-xs text-gray-600">{{ $slot }}</div>
    @endif
</div>
