<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-white">Feature Flags</h1>
        <p class="mt-1 text-sm text-gray-400">All registered feature flags and their current operational state.</p>
    </div>

    @if(empty($rows))
        <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-8 text-center">
            <p class="text-sm text-gray-300">No feature flags found in the database yet.</p>
            <p class="mt-1 text-xs text-gray-500">Register flags in <code class="text-violet-400">config/ab-testing.php</code> and run <code class="text-violet-400">php artisan ab:cache</code>.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-lg border border-gray-700 bg-gray-900">
            <table class="min-w-full divide-y divide-gray-700/60">
                <thead>
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Flag</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Rollout</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Kill Switch</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Last Updated</th>
                        <th class="relative px-6 py-3"><span class="sr-only">View</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700/40">
                    @foreach($rows as $row)
                        @php
                            /** @var \ABTests\Infrastructure\Database\Models\FeatureFlagStateModel $model */
                            $model = $row['model'];
                            $definition = $row['definition'];
                            $displayName = $definition?->name ?? $model->key;
                        @endphp
                        <tr class="hover:bg-gray-800/50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-100">{{ $displayName }}</div>
                                <div class="text-xs text-gray-500 font-mono mt-0.5">{{ $model->key }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <x-ab-testing::flag-status-badge
                                    :enabled="$model->is_enabled"
                                    :killed="$model->killed_at !== null"
                                />
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-300 tabular-nums">
                                {{ $model->rollout_percentage }}%
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($model->killed_at !== null)
                                    <span class="inline-flex items-center rounded-full bg-red-900/60 px-2.5 py-0.5 text-xs font-medium text-red-300"
                                          title="{{ $model->killed_at->toDateTimeString() }}">
                                        Active
                                    </span>
                                @else
                                    <span class="text-gray-600 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                {{ $model->updated_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-right text-sm">
                                <a href="{{ route('ab-testing.feature-flag.show', $model->key) }}"
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
