<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('un usuario interno puede cualquier habilidad sobre cualquier reporte', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $report = Report::factory()->create();

    expect($admin->can('view', $report))->toBeTrue()
        ->and($admin->can('viewAny', Report::class))->toBeTrue()
        ->and($admin->can('deleteAny', Report::class))->toBeTrue();
});

test('un usuario del portal solo puede ver reportes de proyectos de su propio cliente', function () {
    $client = Client::factory()->create();
    $otherClient = Client::factory()->create();

    $portalUser = User::factory()->create(['role' => UserRole::Client, 'client_id' => $client->id]);

    $ownProject = Project::factory()->create(['client_id' => $client->id]);
    $ownReport = Report::factory()->create(['project_id' => $ownProject->id]);

    $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);
    $otherReport = Report::factory()->create(['project_id' => $otherProject->id]);

    expect($portalUser->can('view', $ownReport))->toBeTrue()
        ->and($portalUser->can('view', $otherReport))->toBeFalse()
        ->and($portalUser->can('viewAny', Report::class))->toBeTrue();
});
