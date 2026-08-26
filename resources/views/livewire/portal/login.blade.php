<div class="bg-white shadow-sm rounded-lg p-8 border border-gray-200">
    <h1 class="text-lg font-semibold text-gray-900 mb-6">{{ __('portal.login.heading') }}</h1>

    <form wire:submit="authenticate" class="space-y-4">
        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">{{ __('portal.login.email') }}</label>
            <input
                wire:model="email"
                type="email"
                id="email"
                autocomplete="username"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
            >
            @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700">{{ __('portal.login.password') }}</label>
            <input
                wire:model="password"
                type="password"
                id="password"
                autocomplete="current-password"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 sm:text-sm"
            >
            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <button
            type="submit"
            class="w-full rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-600"
        >
            {{ __('portal.login.submit') }}
        </button>
    </form>
</div>
