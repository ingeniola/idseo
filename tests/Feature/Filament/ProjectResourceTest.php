<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un usuario interno puede ver el listado de proyectos', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Project::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get('/admin/projects')
        ->assertSuccessful();
});

test('el formulario de creacion de proyecto carga para un usuario interno', function () {
    $analyst = User::factory()->create(['role' => UserRole::Analyst]);

    $this->actingAs($analyst)
        ->get('/admin/projects/create')
        ->assertSuccessful();
});

test('asignar usuarios internos a un proyecto usa la tabla pivote project_user', function () {
    $project = Project::factory()->create();
    $user = User::factory()->create(['role' => UserRole::Analyst]);

    $project->users()->attach($user);

    expect($project->users()->count())->toBe(1)
        ->and($user->projects()->first()->is($project))->toBeTrue();
});
