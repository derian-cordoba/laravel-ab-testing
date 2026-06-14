<div>
    {{-- Page header --}}
    <div class="mb-7">
        <h1 class="text-2xl font-semibold text-white">Segments</h1>
        <p class="mt-1 text-sm text-gray-400">
            Audience targeting rules declared on each registered experiment definition.
        </p>
    </div>

    @if(empty($rows))
        <x-ab-testing::empty-state
            message="No registered experiments found."
            hint="Register experiments with #[AsExperiment] and define audience() to see their segments here."
        />
    @else
        {{-- Segmented experiments --}}
        @php $segmented = array_filter($rows, fn($r) => !$r['isOpenAudience']); @endphp
        @php $open = array_filter($rows, fn($r) => $r['isOpenAudience']); @endphp

        @if(!empty($segmented))
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-600">
                Targeted audience ({{ count($segmented) }})
            </h2>
            <div class="space-y-3 mb-8">
                @foreach($segmented as $row)
                    <div class="rounded-lg border border-gray-700 bg-gray-900 overflow-hidden">
                        <div class="flex items-center justify-between gap-4 px-5 py-3.5 border-b border-gray-700/50">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('ab-testing.experiments.show', $row['key']) }}"
                                   class="text-sm font-medium text-gray-100 hover:text-violet-300 transition-colors">
                                    {{ $row['name'] }}
                                </a>
                                <div x-data="{}" class="mt-0.5 flex items-center gap-1.5">
                                    <code class="font-mono text-xs text-gray-500">{{ $row['key'] }}</code>
                                    <button
                                        type="button"
                                        title="Copy key"
                                        x-data="{ copied: false }"
                                        @click="navigator.clipboard && navigator.clipboard.writeText('{{ $row['key'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="shrink-0 rounded p-0.5 text-gray-700 hover:text-gray-400 transition-colors"
                                    >
                                        <svg x-show="!copied" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/>
                                        </svg>
                                        <svg x-show="copied" class="h-3 w-3 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            @if($row['status'])
                                <x-ab-testing::status-badge :status="$row['status']" />
                            @endif
                        </div>

                        {{-- Criteria table --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-700/40">
                                <thead>
                                    <tr>
                                        <th class="px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-600">Attribute</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-600">Operator</th>
                                        <th class="px-5 py-2 text-left text-xs font-medium uppercase tracking-wide text-gray-600">Expected value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-700/30">
                                    @foreach($row['criteria'] as $criterion)
                                        <tr>
                                            <td class="px-5 py-2.5 font-mono text-xs text-violet-300">{{ $criterion['attribute'] }}</td>
                                            <td class="px-5 py-2.5 text-xs text-gray-500">
                                                <span class="inline-flex items-center rounded bg-gray-800 border border-gray-700 px-1.5 py-0.5 font-mono text-xs text-gray-400">
                                                    {{ $criterion['operator'] }}
                                                </span>
                                            </td>
                                            <td class="px-5 py-2.5 font-mono text-xs text-gray-300">{{ $criterion['expected'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Open audience experiments --}}
        @if(!empty($open))
            <h2 class="mb-3 text-xs font-semibold uppercase tracking-widest text-gray-600">
                Open audience — all traffic ({{ count($open) }})
            </h2>
            <div class="rounded-lg border border-gray-700 bg-gray-900 divide-y divide-gray-700/40">
                @foreach($open as $row)
                    <div class="flex items-center gap-4 px-5 py-3 hover:bg-gray-800/30 transition-colors">
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('ab-testing.experiments.show', $row['key']) }}"
                               class="text-sm font-medium text-gray-300 hover:text-violet-300 transition-colors">
                                {{ $row['name'] }}
                            </a>
                            <p class="font-mono text-xs text-gray-600 truncate">{{ $row['key'] }}</p>
                        </div>
                        @if($row['status'])
                            <x-ab-testing::status-badge :status="$row['status']" />
                        @endif
                        <span class="text-xs text-gray-600">No criteria — all units eligible</span>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
