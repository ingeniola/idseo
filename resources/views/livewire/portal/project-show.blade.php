<div>
    <a href="{{ route('portal.dashboard') }}" class="text-sm text-amber-600 hover:text-amber-700">&larr; {{ __('portal.projects.back') }}</a>

    <h1 class="text-2xl font-semibold text-gray-900 mt-2">{{ $project->name }}</h1>
    <p class="text-gray-500 mb-8">{{ $project->domain }}</p>

    <section class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('portal.visibility.heading') }}</h2>

        @if (! $hasVisibilityData)
            <p class="text-gray-500 text-sm">{{ __('portal.visibility.empty') }}</p>
        @else
            <svg width="{{ $chart['width'] }}" height="{{ $chart['height'] }}" viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="max-w-full">
                <polyline
                    points="{{ $chart['polyline'] }}"
                    fill="none"
                    stroke="#f59e0b"
                    stroke-width="2"
                />
                @foreach ($chart['dots'] as $dot)
                    <circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="3" fill="#f59e0b" />
                @endforeach
                @foreach ($chart['labels'] as $label)
                    <text x="{{ $label['x'] }}" y="{{ $chart['height'] - 8 }}" font-size="9" fill="#9ca3af" text-anchor="middle">{{ $label['label'] }}</text>
                @endforeach
            </svg>
        @endif
    </section>

    <section class="bg-white border border-gray-200 rounded-lg p-6 mb-8">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('portal.keywords.heading') }}</h2>

        @if ($keywords->isEmpty())
            <p class="text-gray-500 text-sm">{{ __('portal.keywords.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 text-xs uppercase border-b border-gray-200">
                            <th class="py-2 pr-4">{{ __('portal.keywords.columns.keyword') }}</th>
                            <th class="py-2 pr-4">{{ __('portal.keywords.columns.position') }}</th>
                            <th class="py-2 pr-4">{{ __('portal.keywords.columns.change') }}</th>
                            <th class="py-2 pr-4">{{ __('portal.keywords.columns.url') }}</th>
                            <th class="py-2 pr-4">{{ __('portal.keywords.columns.volume') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($keywords as $keyword)
                            @php
                                $latest = $keyword->latestRanking;
                                $delta = ($latest && $latest->position !== null && $latest->previous_position !== null)
                                    ? $latest->previous_position - $latest->position
                                    : null;
                            @endphp
                            <tr class="border-b border-gray-100">
                                <td class="py-2 pr-4">{{ $keyword->keyword }}</td>
                                <td class="py-2 pr-4">{{ $latest?->position ?? '—' }}</td>
                                <td class="py-2 pr-4">
                                    @if ($delta === null)
                                        —
                                    @elseif ($delta > 0)
                                        <span class="text-emerald-600">▲ {{ $delta }}</span>
                                    @elseif ($delta < 0)
                                        <span class="text-red-600">▼ {{ abs($delta) }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-gray-500 truncate max-w-xs">{{ $latest?->url ?? '—' }}</td>
                                <td class="py-2 pr-4">{{ $keyword->search_volume !== null ? number_format($keyword->search_volume) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="bg-white border border-gray-200 rounded-lg p-6">
        <h2 class="font-semibold text-gray-900 mb-4">{{ __('portal.reports.heading') }}</h2>

        @if ($reports->isEmpty())
            <p class="text-gray-500 text-sm">{{ __('portal.reports.empty') }}</p>
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($reports as $report)
                    <li class="py-3 flex items-center justify-between">
                        <span class="text-sm text-gray-700">
                            {{ \Illuminate\Support\Carbon::parse($report->period_start)->translatedFormat('d \d\e F') }}
                            &mdash;
                            {{ \Illuminate\Support\Carbon::parse($report->period_end)->translatedFormat('d \d\e F \d\e Y') }}
                        </span>
                        <a href="{{ route('reports.download', $report) }}" class="text-sm text-amber-600 hover:text-amber-700">
                            {{ __('portal.reports.download') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
