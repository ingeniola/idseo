<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('portal.title') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body class="min-h-screen bg-gray-50">
    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="{{ route('portal.dashboard') }}" class="flex items-center gap-3">
                @if ($logoDataUri = \App\Support\StoredFileDataUri::from(auth()->user()?->client?->logo_path))
                    <img src="{{ $logoDataUri }}" alt="{{ auth()->user()->client->name }}" class="h-8">
                @else
                    <span class="font-semibold text-gray-900">{{ auth()->user()?->client?->name }}</span>
                @endif
            </a>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">
                    {{ __('portal.nav.logout') }}
                </button>
            </form>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 py-8">
        {{ $slot }}
    </main>
</body>
</html>
