<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\RankingAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('cualquier usuario interno puede ver el listado de alertas', function () {
    $analyst = User::factory()->create(['role' => UserRole::Analyst]);
    RankingAlert::factory()->count(2)->create();

    $this->actingAs($analyst)
        ->get('/admin/ranking-alerts')
        ->assertSuccessful();
});

test('un usuario de cliente no puede ver el panel interno ni sus alertas', function () {
    $client = User::factory()->create(['role' => UserRole::Client]);

    $this->actingAs($client)
        ->get('/admin/ranking-alerts')
        ->assertForbidden();
});
