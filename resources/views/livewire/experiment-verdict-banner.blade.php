@use(ABTests\Enums\Verdict)

<div>
    @if(!$hasData)
        {{-- No rollup data yet --}}
        <div class="flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-900/60 px-5 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-700/60">
                <svg class="h-4 w-4 text-gray-400 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-gray-300">Collecting data</p>
                <p class="text-xs text-gray-500 mt-0.5">Results will appear once the rollup job has processed enough events.</p>
            </div>
        </div>

    @elseif($srmDetected)
        {{-- SRM overrides verdict --}}
        <div class="flex items-start gap-3 rounded-lg border border-orange-700/50 bg-orange-900/20 px-5 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-900/60">
                <svg class="h-4 w-4 text-orange-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-orange-300">Results may not be valid — Sample Ratio Mismatch detected</p>
                <p class="text-xs text-orange-400/80 mt-1">
                    The observed assignment proportions differ significantly from the configured weights.
                    Investigate the assignment pipeline before drawing any conclusions.
                    See the Trust panel below for details.
                </p>
            </div>
        </div>

    @elseif($overallVerdict === Verdict::ship)
        <div class="flex items-start gap-3 rounded-lg border border-green-700/50 bg-green-900/20 px-5 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-900/60">
                <svg class="h-4 w-4 text-green-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-green-300">Significant positive result — consider shipping</p>
                <p class="text-xs text-green-400/80 mt-1">
                    {{ number_format($totalUnits) }} units &middot;
                    {{ $treatmentCount }} treatment {{ $treatmentCount === 1 ? 'arm' : 'arms' }}.
                    The treatment outperforms control with statistical confidence. Review the results table before deciding.
                </p>
            </div>
        </div>

    @elseif($overallVerdict === Verdict::doNotShip)
        <div class="flex items-start gap-3 rounded-lg border border-red-700/50 bg-red-900/20 px-5 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-red-900/60">
                <svg class="h-4 w-4 text-red-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-red-300">Significant negative result — do not ship</p>
                <p class="text-xs text-red-400/80 mt-1">
                    {{ number_format($totalUnits) }} units &middot;
                    {{ $treatmentCount }} treatment {{ $treatmentCount === 1 ? 'arm' : 'arms' }}.
                    The treatment performs significantly worse than control. Do not launch.
                </p>
            </div>
        </div>

    @elseif($overallVerdict === Verdict::inconclusive)
        <div class="flex items-start gap-3 rounded-lg border border-yellow-700/50 bg-yellow-900/20 px-5 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-yellow-900/60">
                <svg class="h-4 w-4 text-yellow-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-yellow-300">Inconclusive — more data needed</p>
                <p class="text-xs text-yellow-400/80 mt-1">
                    {{ number_format($totalUnits) }} units &middot;
                    {{ $treatmentCount }} treatment {{ $treatmentCount === 1 ? 'arm' : 'arms' }}.
                    No statistically significant difference detected yet. Continue running the experiment.
                </p>
            </div>
        </div>

    @else
        {{-- Results exist but no treatment arms have a verdict yet --}}
        <div class="flex items-center gap-3 rounded-lg border border-gray-700 bg-gray-900/60 px-5 py-4">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-700/60">
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-medium text-gray-300">Analysis in progress</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ number_format($totalUnits) }} units assigned. Verdict will be available once sufficient data is collected.
                </p>
            </div>
        </div>
    @endif
</div>
