@use(ABTests\Enums\Verdict)
@use(ABTests\Presentation\Support\VerdictPresenter)

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ VerdictPresenter::badgeClass($verdict) }}">
    {{ $verdict->label() }}
</span>
