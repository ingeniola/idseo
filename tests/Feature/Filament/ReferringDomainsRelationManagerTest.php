<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\ReferringDomainsRelationManager;
use App\Models\Project;
use App\Models\ReferringDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('lista los dominios referentes del proyecto y no los de otro', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();
    $domain = ReferringDomain::factory()->create(['project_id' => $project->id]);

    $otherProject = Project::factory()->create();
    $foreignDomain = ReferringDomain::factory()->create(['project_id' => $otherProject->id]);

    $livewire = Livewire::actingAs($admin)->test(ReferringDomainsRelationManager::class, [
        'ownerRecord' => $project,
        'pageClass' => EditProject::class,
    ]);

    $livewire->assertCanSeeTableRecords([$domain]);
    $livewire->assertCanNotSeeTableRecords([$foreignDomain]);
});
