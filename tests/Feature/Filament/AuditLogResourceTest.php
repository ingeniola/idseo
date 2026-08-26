<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un admin puede ver el listado de auditoria', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    AuditLog::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertSuccessful();
});

test('un usuario interno que no es admin no puede ver la auditoria', function () {
    $analyst = User::factory()->create(['role' => UserRole::Analyst]);

    $this->actingAs($analyst)
        ->get('/admin/audit-logs')
        ->assertForbidden();
});
