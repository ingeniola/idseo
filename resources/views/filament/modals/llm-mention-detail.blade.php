<div class="max-h-96 space-y-4 overflow-y-auto text-sm">
    <div>
        <p class="font-medium">{{ __('llm_mentions.fields.question') }}</p>
        <p class="text-gray-500 dark:text-gray-400">{{ $mention->question ?? '—' }}</p>
    </div>
    <div>
        <p class="font-medium">{{ __('llm_mentions.fields.answer') }}</p>
        <p class="whitespace-pre-line text-gray-500 dark:text-gray-400">{{ $mention->answer ?? '—' }}</p>
    </div>
    <div>
        <p class="font-medium">{{ __('llm_mentions.fields.sources') }}</p>
        @if (empty($mention->sources))
            <p class="text-gray-500 dark:text-gray-400">{{ __('llm_mentions.view.no_sources') }}</p>
        @else
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($mention->sources as $source)
                    <li class="break-all text-gray-500 dark:text-gray-400">
                        {{ is_array($source) ? ($source['url'] ?? $source['domain'] ?? json_encode($source)) : $source }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
