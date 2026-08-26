<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un usuario interno puede cualquier habilidad sobre cualquier proyecto', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();

    $policy = new ProjectPolicy;

    expect($policy->view($admin, $project))->toBeFalse() // el metodo especifico rechaza...
        ->and($admin->can('view', $project))->toBeTrue() // ...pero before() lo permite igual vía el Gate.
        ->and($admin->can('viewAny', Project::class))->toBeTrue()
        ->and($admin->can('update', $project))->toBeTrue()
        ->and($admin->can('delete', $project))->toBeTrue();
});

test('un usuario del portal puede ver solo los proyectos de su propio cliente', function () {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();

    $portalUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);
    $ownProject = Project::factory()->create(['client_id' => $client->id]);
    $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);

    expect($portalUser->can('view', $ownProject))->toBeTrue()
        ->and($portalUser->can('view', $otherProject))->toBeFalse()
        ->and($portalUser->can('viewAny', Project::class))->toBeTrue();
});

test('un usuario del portal no puede crear, editar ni borrar proyectos', function () {
    $client = Client::factory()->create();
    $portalUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);
    $project = Project::factory()->create(['client_id' => $client->id]);

    expect($portalUser->can('create', Project::class))->toBeFalse()
        ->and($portalUser->can('update', $project))->toBeFalse()
        ->and($portalUser->can('delete', $project))->toBeFalse();
});
