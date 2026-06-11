<div>
    @if(empty($rows))
        {{-- No data --}}
    @else
        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            {{-- Header --}}
            <div class="flex items-center justify-between border-b border-gray-700/60 px-6 py-4">
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-gray-100">Trust</h2>
                    <span class="text-xs text-gray-500">Sample ratio mismatch check</span>
                </div>

                @if($srmResult !== null)
                    @if($srmResult->detected)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-orange-900/60 px-3 py-1 text-xs font-medium text-orange-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-orange-400"></span>
                            SRM detected
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-green-900/60 px-3 py-1 text-xs font-medium text-green-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-400"></span>
                            No SRM
                        </span>
                    @endif
                @endif
            </div>

            {{-- Per-variant allocation table --}}
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-700/60">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Variant</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Intended</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Observed units</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Observed %</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Expected units</th>
                            <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Deviation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/40">
                        @foreach($rows as $variantKey => $row)
                            @php
                                $deviation = $row['expected'] > 0
                                    ? ($row['observed'] - $row['expected']) / $row['expected']
                                    : 0;
                                $deviationAbs = abs($deviation);
                                $deviationClass = $deviationAbs > 0.05
                                    ? 'text-orange-400'
                                    : ($deviationAbs > 0.02 ? 'text-yellow-400' : 'text-gray-400');
                            @endphp
                            <tr class="hover:bg-gray-800/30 transition-colors">
                                <td class="px-6 py-3 font-mono text-sm text-gray-200">{{ $variantKey }}</td>
                                <td class="px-6 py-3 text-right text-sm text-gray-400 tabular-nums">{{ $row['intended_weight'] }}%</td>
                                <td class="px-6 py-3 text-right text-sm text-gray-200 tabular-nums font-medium">{{ number_format($row['observed']) }}</td>
                                <td class="px-6 py-3 text-right text-sm tabular-nums
                                    {{ abs($row['observed_percent'] - $row['intended_weight']) > 2 ? 'text-orange-400' : 'text-gray-300' }}">
                                    {{ number_format($row['observed_percent'], 1) }}%
                                </td>
                                <td class="px-6 py-3 text-right text-sm text-gray-500 tabular-nums">{{ number_format($row['expected']) }}</td>
                                <td class="px-6 py-3 text-right text-sm tabular-nums font-medium {{ $deviationClass }}">
                                    {{ $deviation >= 0 ? '+' : '' }}{{ number_format($deviation * 100, 1) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-700/60 bg-gray-800/20">
                            <td class="px-6 py-3 text-xs text-gray-500 font-medium">Total</td>
                            <td class="px-6 py-3 text-right text-xs text-gray-500">100%</td>
                            <td class="px-6 py-3 text-right text-sm font-semibold text-gray-200 tabular-nums">{{ number_format($totalUnits) }}</td>
                            <td class="px-6 py-3 text-right text-xs text-gray-500">100%</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Chi-square summary footer --}}
            @if($srmResult !== null)
                <div class="border-t border-gray-700/60 px-6 py-3 flex flex-wrap items-center gap-x-6 gap-y-2 text-xs text-gray-500">
                    <span>
                        Chi-square:
                        <strong class="text-gray-300 tabular-nums">{{ number_format($srmResult->chiSquare, 3) }}</strong>
                    </span>
                    <span>
                        p-value:
                        <strong class="{{ $srmResult->pValue < 0.01 ? 'text-orange-400' : 'text-gray-300' }} tabular-nums">
                            {{ number_format($srmResult->pValue, 4) }}
                        </strong>
                    </span>
                    <span>Threshold: <strong class="text-gray-400">p &lt; 0.01</strong></span>

                    @if($srmResult->detected)
                        <span class="text-orange-400">
                            Significant mismatch — investigate your assignment pipeline before interpreting results.
                        </span>
                    @else
                        <span class="text-green-500/80">Assignment proportions are within expected bounds.</span>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
