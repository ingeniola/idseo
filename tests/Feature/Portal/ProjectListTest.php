<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un invitado se redirige al login del portal', function () {
    $this->get(route('portal.dashboard'))
        ->assertRedirect(route('portal.login'));
});

test('un usuario interno no puede entrar al portal', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('portal.dashboard'))
        ->assertForbidden();
});

test('muestra solo los proyectos del cliente del usuario', function () {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();

    $ownProject = Project::factory()->create(['client_id' => $client->id, 'name' => 'Proyecto propio']);
    Project::factory()->create(['client_id' => $otherClient->id, 'name' => 'Proyecto ajeno']);

    ProjectVisibilitySnapshot::factory()->create(['project_id' => $ownProject->id, 'calculated_at' => '2026-08-20', 'visibility_score' => 42.5]);

    $user = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertSuccessful()
        ->assertSee('Proyecto propio')
        ->assertDontSee('Proyecto ajeno')
        ->assertSee('42.5')
        ->assertDontSee('DataForSEO', false);
});
