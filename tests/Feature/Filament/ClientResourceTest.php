<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un usuario interno puede ver el listado de clientes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Client::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get('/admin/clients')
        ->assertSuccessful();
});

test('un usuario con rol client no puede entrar al panel interno', function () {
    $client = Client::factory()->create();
    $clientUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $this->actingAs($clientUser)
        ->get('/admin/clients')
        ->assertForbidden();
});

test('un usuario no autenticado es redirigido al login del panel', function () {
    $this->get('/admin/clients')
        ->assertRedirect('/admin/login');
});

test('el formulario de creacion de cliente carga para un usuario interno', function () {
    $manager = User::factory()->create(['role' => UserRole::Manager]);

    $this->actingAs($manager)
        ->get('/admin/clients/create')
        ->assertSuccessful();
});
