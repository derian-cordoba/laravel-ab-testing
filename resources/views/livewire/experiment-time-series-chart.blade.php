<div>
    @if(empty($series))
        {{-- Hidden when not enough data —— renders nothing --}}
    @else
        @php
            // Chart geometry
            $padLeft   = 46;  // room for Y-axis labels
            $padRight  = 16;
            $padTop    = 16;
            $padBottom = 36;  // room for X-axis labels
            $width     = 640;
            $height    = 200;
            $plotW     = $width  - $padLeft - $padRight;
            $plotH     = $height - $padTop  - $padBottom;
            $dateCount = count($dates);

            // Y-axis: 0 to ceil(max rate / 5) * 5 to get a round ceiling
            $allPoints = array_merge(...array_column($series, 'points'));
            $maxRate   = max($allPoints ?: [0]);
            $yMax      = max(5, ceil($maxRate / 5) * 5);  // round up to nearest 5%

            // Helpers
            $xPos = static fn (int $i): float =>
                $padLeft + ($dateCount > 1 ? $i / ($dateCount - 1) * $plotW : $plotW / 2);

            $yPos = static fn (float $rate): float =>
                $padTop + $plotH - ($rate / $yMax * $plotH);

            // X-axis: show at most 7 evenly-spaced labels
            $labelStep = max(1, (int) ceil($dateCount / 7));
            $xLabels   = [];
            for ($i = 0; $i < $dateCount; $i += $labelStep) {
                $xLabels[] = $i;
            }
            if (end($xLabels) !== $dateCount - 1) {
                $xLabels[] = $dateCount - 1;
            }

            // Y-axis: 5 gridlines
            $yGridLines = [];
            for ($v = 0; $v <= $yMax; $v += max(1, (int) round($yMax / 4))) {
                $yGridLines[] = $v;
            }
        @endphp

        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-700/60 px-6 py-4">
                <h2 class="font-semibold text-gray-100">Conversion rate over time</h2>
                <span class="text-xs text-gray-500">cumulative · {{ count($dates) }} days</span>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-1 px-6 pt-4 pb-1">
                @foreach($series as $s)
                    <span class="flex items-center gap-1.5 text-xs text-gray-400">
                        <span class="inline-block h-2 w-5 rounded-full" style="background:{{ $s['color'] }}"></span>
                        <span class="font-mono">{{ $s['key'] }}</span>
                    </span>
                @endforeach
            </div>

            {{-- SVG chart --}}
            <div class="px-4 pb-4">
                <svg viewBox="0 0 {{ $width }} {{ $height }}"
                     class="w-full"
                     aria-label="Conversion rate time-series chart"
                     role="img">

                    {{-- Y-axis gridlines and labels --}}
                    @foreach($yGridLines as $v)
                        @php $y = $yPos($v); @endphp
                        <line x1="{{ $padLeft }}" y1="{{ $y }}"
                              x2="{{ $width - $padRight }}" y2="{{ $y }}"
                              stroke="#374151" stroke-width="1"/>
                        <text x="{{ $padLeft - 6 }}" y="{{ $y + 4 }}"
                              text-anchor="end" font-size="10" fill="#6b7280">{{ $v }}%</text>
                    @endforeach

                    {{-- X-axis baseline --}}
                    <line x1="{{ $padLeft }}" y1="{{ $padTop + $plotH }}"
                          x2="{{ $width - $padRight }}" y2="{{ $padTop + $plotH }}"
                          stroke="#4b5563" stroke-width="1"/>

                    {{-- X-axis labels --}}
                    @foreach($xLabels as $i)
                        @php
                            $x = $xPos($i);
                            // Short date: strip year if same as first date's year
                            $label = strlen($dates[$i]) >= 10
                                ? substr($dates[$i], 5)   // MM-DD
                                : $dates[$i];
                        @endphp
                        <text x="{{ $x }}" y="{{ $height - 4 }}"
                              text-anchor="middle" font-size="10" fill="#6b7280">{{ $label }}</text>
                    @endforeach

                    {{-- Series lines --}}
                    @foreach($series as $s)
                        @php
                            $polyPoints = collect($s['points'])->map(
                                static fn ($rate, $i) => $xPos($i) . ',' . $yPos($rate)
                            )->implode(' ');
                        @endphp

                        {{-- Line --}}
                        <polyline points="{{ $polyPoints }}"
                                  fill="none"
                                  stroke="{{ $s['color'] }}"
                                  stroke-width="2"
                                  stroke-linejoin="round"
                                  stroke-linecap="round"/>

                        {{-- Dots (only for smaller datasets to avoid clutter) --}}
                        @if(count($dates) <= 30)
                            @foreach($s['points'] as $i => $rate)
                                <circle cx="{{ $xPos($i) }}" cy="{{ $yPos($rate) }}"
                                        r="3" fill="{{ $s['color'] }}"
                                        class="opacity-70"/>
                            @endforeach
                        @endif
                    @endforeach
                </svg>
            </div>
        </div>
    @endif
</div>
