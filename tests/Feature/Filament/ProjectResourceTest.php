<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Models\Language;
use App\Models\Project;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

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

test('la pagina de edicion de proyecto carga con la seccion de keywords', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/projects/{$project->id}/edit")
        ->assertSuccessful();
});

test('el selector de idioma del proyecto solo ofrece idiomas validos para keywords_data/google_ads', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    Language::query()->create(['language_code' => 'es', 'language_name' => 'Spanish', 'valid_for_google_ads_keywords' => true]);
    Language::query()->create(['language_code' => 'es-419', 'language_name' => 'Spanish (Latin America)', 'valid_for_google_ads_keywords' => false]);

    Livewire::actingAs($admin)
        ->test(CreateProject::class)
        ->assertFormFieldExists(
            'default_language_code',
            fn (Select $field) => array_key_exists('es', $field->getOptions())
                && ! array_key_exists('es-419', $field->getOptions()),
        );
});

test('la pagina de edicion de proyecto incluye la grafica de visibilidad agregada', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->get("/admin/projects/{$project->id}/edit")
        ->assertSuccessful()
        ->assertSee(__('projects.visibility_chart.heading'));
});
