@use(ABTests\Enums\Verdict)

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $verdict->badgeClass() }}">
    {{ $verdict->label() }}
</span>
