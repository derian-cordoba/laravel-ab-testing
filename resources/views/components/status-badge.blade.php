@use(ABTests\Enums\ExperimentStatus)

@php
    $statusEnum = $status instanceof ExperimentStatus
        ? $status
        : ExperimentStatus::tryFrom($status);
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusEnum?->badgeClass() ?? 'bg-gray-700 text-gray-300' }}">
    {{ $statusEnum?->label() ?? ucfirst($status) }}
</span>
