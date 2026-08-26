<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * User::canAccessPanel() es el gate que usa Filament (Login::authenticate(),
 * su middleware Authenticate, etc.) para bloquear el panel interno a
 * cuentas de cliente — nunca pasa por EnsureUserIsClient ni por ningún
 * otro punto ya auditado, así que el log tiene que salir de ahí mismo.
 */
test('un usuario de cliente que intenta entrar al panel interno queda registrado', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);

    $this->actingAs($client)
        ->get('/admin')
        ->assertForbidden();

    expect(
        AuditLog::query()
            ->where('event', AuditEvent::AuthorizationDenied)
            ->where('user_id', $client->id)
            ->where('context->reason', 'not_internal_role')
            ->exists()
    )->toBeTrue();
});

test('un usuario interno no genera ruido en la auditoria al entrar al panel', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();

    expect(AuditLog::query()->where('event', AuditEvent::AuthorizationDenied)->exists())->toBeFalse();
});
