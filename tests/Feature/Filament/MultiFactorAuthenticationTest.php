<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('un usuario interno sin 2FA configurado se redirige a configurarlo', function () {
    $admin = User::factory()->withoutMultiFactorAuthentication()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect()
        ->assertRedirectContains('multi-factor-authentication');
});

test('un usuario interno con 2FA configurado entra normalmente', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    expect($admin->app_authentication_secret)->not->toBeNull();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});

test('el secreto de 2FA se guarda encriptado en la base de datos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $raw = DB::table('users')->where('id', $admin->id)->value('app_authentication_secret');

    expect($raw)->not->toBe($admin->app_authentication_secret)
        ->and($raw)->not->toContain($admin->app_authentication_secret);
});
