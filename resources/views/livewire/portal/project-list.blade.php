<div>
    <h1 class="text-2xl font-semibold text-gray-900 mb-6">{{ __('portal.projects.heading') }}</h1>

    @if ($projects->isEmpty())
        <p class="text-gray-500">{{ __('portal.projects.empty') }}</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($projects as $project)
                <a
                    href="{{ route('portal.projects.show', $project) }}"
                    class="block bg-white border border-gray-200 rounded-lg p-5 hover:border-amber-400 hover:shadow-sm transition"
                >
                    <h2 class="font-semibold text-gray-900">{{ $project->name }}</h2>
                    <p class="text-sm text-gray-500">{{ $project->domain }}</p>

                    @if ($project->latestVisibilitySnapshot)
                        <p class="mt-3 text-2xl font-bold text-gray-900">
                            {{ number_format((float) $project->latestVisibilitySnapshot->visibility_score, 1) }}
                        </p>
                        <p class="text-xs text-gray-500">{{ __('portal.projects.visibility_score') }}</p>
                    @else
                        <p class="mt-3 text-sm text-gray-400">{{ __('portal.projects.no_data') }}</p>
                    @endif
                </a>
            @endforeach
        </div>
    @endif
</div>
