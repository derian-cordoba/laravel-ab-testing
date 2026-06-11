@use(ABTests\Infrastructure\Database\Models\ExperimentModel)

<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Experiments</h1>
        <p class="mt-1 text-sm text-gray-400">All registered experiments and their current operational state.</p>
    </div>

    @if(empty($rows))
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-8 text-center">
            <p class="text-sm text-gray-300">No experiments found in the database yet.</p>
            <p class="mt-1 text-xs text-gray-500">Register experiments in your config or run migrations to get started.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            <table class="min-w-full divide-y divide-gray-700/60">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Experiment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Traffic</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Kill Switch</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500">Assigned</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Started</th>
                        <th class="relative px-6 py-3"><span class="sr-only">View</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/40">
                    @foreach($rows as $row)
                        @php
                            /** @var ExperimentModel $model */
                            $model = $row['model'];
                            $definition = $row['definition'];
                            $displayName = $definition?->name ?? $model->key;
                        @endphp
                        <tr class="hover:bg-gray-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-100">{{ $displayName }}</div>
                                <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $model->key }}</div>
                                @if($definition?->layer)
                                    <span class="inline-flex items-center rounded bg-gray-700 px-1.5 py-0.5 text-xs text-gray-300 mt-1">
                                        Layer: {{ $definition->layer }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <x-ab-testing::status-badge :status="$model->status" />
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-300">
                                {{ $model->traffic_percentage }}%
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($model->is_killed)
                                    <span class="inline-flex items-center rounded-full bg-red-900/60 px-2.5 py-0.5 text-xs font-medium text-red-300">
                                        Killed
                                    </span>
                                @else
                                    <span class="text-gray-600 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right text-sm tabular-nums text-gray-300">
                                {{ number_format($assignedCounts[$model->key] ?? 0) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $model->started_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('ab-testing.experiment.show', $model->key) }}"
                                   class="text-violet-400 hover:text-violet-200 font-medium transition-colors">
                                    View →
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
