<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Enums\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Login separado del panel interno (sección 5.5 del SPEC). Solo
 * autentica usuarios con rol `client` — unas credenciales válidas de
 * un usuario interno se rechazan con el mismo mensaje genérico que
 * unas credenciales incorrectas, para no filtrar qué cuentas existen
 * ni a qué panel pertenecen.
 */
#[Layout('layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        if (Auth::check() && Auth::user()->role === UserRole::Client) {
            $this->redirect(route('portal.dashboard'), navigate: false);
        }
    }

    public function authenticate(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials = ['email' => $this->email, 'password' => $this->password];

        if (! Auth::attempt($credentials)) {
            $this->addError('email', __('portal.login.invalid_credentials'));

            return;
        }

        if (Auth::user()->role !== UserRole::Client) {
            Auth::logout();
            $this->addError('email', __('portal.login.invalid_credentials'));

            return;
        }

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('portal.dashboard'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.portal.login');
    }
}
