<div x-data="{ open: false }" class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">

    {{-- Accordion header --}}
    <div @click="open = !open"
         role="button" tabindex="0" @keydown.enter="open = !open"
         class="flex items-center justify-between min-h-[3.5rem] px-6 py-4 cursor-pointer select-none hover:bg-gray-800/40 transition-colors"
         :class="open ? 'border-b border-gray-700/60' : ''">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-gray-100">Conversion Rate Over Time</h2>
            @if(!empty($series))
                <span class="text-xs text-gray-500">cumulative &middot; {{ count($dates) }} days</span>
            @else
                <span class="text-xs text-gray-500">time series</span>
            @endif
        </div>
        <svg :class="open ? 'rotate-180' : ''"
             class="h-4 w-4 shrink-0 text-gray-500 transition-transform duration-150"
             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/>
        </svg>
    </div>

    <div x-show="open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">

        @if(empty($series))
            <div class="p-8 text-center">
                <p class="text-sm text-gray-400">Not enough data to render a time-series chart yet.</p>
            </div>
        @else
            @php
                // Chart geometry
                $padLeft   = 46;
                $padRight  = 16;
                $padTop    = 16;
                $padBottom = 36;
                $width     = 640;
                $height    = 200;
                $plotW     = $width  - $padLeft - $padRight;
                $plotH     = $height - $padTop  - $padBottom;
                $dateCount = count($dates);

                $allPoints = array_merge(...array_column($series, 'points'));
                $maxRate   = max($allPoints ?: [0]);
                $yMax      = max(5, ceil($maxRate / 5) * 5);

                $xPos = static fn (int $i): float =>
                    $padLeft + ($dateCount > 1 ? $i / ($dateCount - 1) * $plotW : $plotW / 2);

                $yPos = static fn (float $rate): float =>
                    $padTop + $plotH - ($rate / $yMax * $plotH);

                $labelStep = max(1, (int) ceil($dateCount / 7));
                $xLabels   = [];
                for ($i = 0; $i < $dateCount; $i += $labelStep) {
                    $xLabels[] = $i;
                }
                if (end($xLabels) !== $dateCount - 1) {
                    $xLabels[] = $dateCount - 1;
                }

                $yGridLines = [];
                for ($v = 0; $v <= $yMax; $v += max(1, (int) round($yMax / 4))) {
                    $yGridLines[] = $v;
                }
            @endphp

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

                    @foreach($yGridLines as $v)
                        @php $y = $yPos($v); @endphp
                        <line x1="{{ $padLeft }}" y1="{{ $y }}"
                              x2="{{ $width - $padRight }}" y2="{{ $y }}"
                              stroke="#374151" stroke-width="1"/>
                        <text x="{{ $padLeft - 6 }}" y="{{ $y + 4 }}"
                              text-anchor="end" font-size="10" fill="#6b7280">{{ $v }}%</text>
                    @endforeach

                    <line x1="{{ $padLeft }}" y1="{{ $padTop + $plotH }}"
                          x2="{{ $width - $padRight }}" y2="{{ $padTop + $plotH }}"
                          stroke="#4b5563" stroke-width="1"/>

                    @foreach($xLabels as $i)
                        @php
                            $x = $xPos($i);
                            $label = strlen($dates[$i]) >= 10
                                ? substr($dates[$i], 5)
                                : $dates[$i];
                        @endphp
                        <text x="{{ $x }}" y="{{ $height - 4 }}"
                              text-anchor="middle" font-size="10" fill="#6b7280">{{ $label }}</text>
                    @endforeach

                    @foreach($series as $s)
                        @php
                            $polyPoints = collect($s['points'])->map(
                                static fn ($rate, $i) => $xPos($i) . ',' . $yPos($rate)
                            )->implode(' ');
                        @endphp

                        <polyline points="{{ $polyPoints }}"
                                  fill="none"
                                  stroke="{{ $s['color'] }}"
                                  stroke-width="2"
                                  stroke-linejoin="round"
                                  stroke-linecap="round"/>

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
        @endif
    </div>
</div>
