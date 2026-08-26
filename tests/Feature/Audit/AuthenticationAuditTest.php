<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

/**
 * $this->actingAs() en las pruebas NO dispara los eventos Login/Failed/
 * Logout de Laravel (asigna el usuario autenticado directamente, sin
 * pasar por Auth::attempt()) — por eso estas pruebas usan Auth::attempt()
 * y Auth::logout() de verdad, igual que hacen el login de Filament y
 * el del portal.
 */
test('un login exitoso queda registrado en la auditoria', function () {
    $user = User::factory()->create(['password' => bcrypt('correcto123')]);

    $attempted = Auth::attempt(['email' => $user->email, 'password' => 'correcto123']);

    expect($attempted)->toBeTrue();

    $log = AuditLog::query()->where('event', AuditEvent::LoginSucceeded)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->email)->toBe($user->email);
});

test('un login fallido queda registrado con el correo pero sin usuario', function () {
    User::factory()->create(['email' => 'existe@ingenio.la', 'password' => bcrypt('correcto123')]);

    Auth::attempt(['email' => 'existe@ingenio.la', 'password' => 'incorrecta']);

    $log = AuditLog::query()->where('event', AuditEvent::LoginFailed)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->email)->toBe('existe@ingenio.la');
});

test('un login fallido con un correo que no existe tambien queda registrado', function () {
    Auth::attempt(['email' => 'nadie@existe.com', 'password' => 'lo-que-sea']);

    $log = AuditLog::query()->where('event', AuditEvent::LoginFailed)->first();

    expect($log)->not->toBeNull()
        ->and($log->email)->toBe('nadie@existe.com');
});

test('un logout queda registrado en la auditoria', function () {
    $user = User::factory()->create();

    Auth::login($user);
    Auth::logout();

    $log = AuditLog::query()->where('event', AuditEvent::Logout)->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id);
});
