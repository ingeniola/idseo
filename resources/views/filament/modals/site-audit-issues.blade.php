<div class="max-h-96 overflow-y-auto space-y-2">
    @if ($issues->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('site_audits.issues.empty') }}</p>
    @else
        @foreach ($issues as $issue)
            <div class="flex flex-col gap-1 border-t border-gray-100 pt-2 text-sm first:border-t-0 first:pt-0 dark:border-white/5">
                <div class="flex flex-wrap items-center gap-2">
                    <x-filament::badge :color="$issue->severity->getColor()">
                        {{ $issue->severity->getLabel() }}
                    </x-filament::badge>
                    <span class="font-medium">{{ $issue->issue_type->getLabel() }}</span>
                </div>
                <span class="break-all text-gray-500 dark:text-gray-400">{{ $issue->url }}</span>
                <span>{{ $issue->message }}</span>
            </div>
        @endforeach
    @endif
</div>
