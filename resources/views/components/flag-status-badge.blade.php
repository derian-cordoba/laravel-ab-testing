@php
    $isKilled = !empty($killed);
    $isEnabled = !$isKilled && !empty($enabled);

    if ($isKilled) {
        $label = 'Killed';
        $classes = 'border-red-700/60 bg-red-900/30 text-red-300';
        $dotClass = 'bg-red-400';
    } elseif ($isEnabled) {
        $label = 'Enabled';
        $classes = 'border-green-700/60 bg-green-900/30 text-green-300';
        $dotClass = 'bg-green-400 animate-pulse';
    } else {
        $label = 'Disabled';
        $classes = 'border-gray-700/60 bg-gray-800/40 text-gray-400';
        $dotClass = 'bg-gray-500';
    }
@endphp

<span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $classes }}">
    <span class="h-1.5 w-1.5 rounded-full {{ $dotClass }}"></span>
    {{ $label }}
</span>
