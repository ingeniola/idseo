<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Portal\Login;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('la pagina de login del portal carga para un invitado', function () {
    $this->get(route('portal.login'))->assertSuccessful();
});

test('un usuario con rol client puede iniciar sesion y llega al escritorio del portal', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id, 'password' => bcrypt('correcto123')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'correcto123')
        ->call('authenticate')
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('una contrasena incorrecta muestra un error generico', function () {
    $user = User::factory()->create(['role' => UserRole::Client, 'password' => bcrypt('correcto123')]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'incorrecta')
        ->call('authenticate')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('un usuario interno con credenciales validas no puede entrar al portal', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'password' => bcrypt('correcto123')]);

    Livewire::test(Login::class)
        ->set('email', $admin->email)
        ->set('password', 'correcto123')
        ->call('authenticate')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('un usuario client ya autenticado que visita login se redirige al escritorio', function () {
    $client = Client::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('portal.login'))
        ->assertRedirect(route('portal.dashboard'));
});
