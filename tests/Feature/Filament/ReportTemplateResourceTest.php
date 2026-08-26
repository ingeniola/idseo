<?php

declare(strict_types=1);

use App\Enums\ReportSection;
use App\Enums\UserRole;
use App\Filament\Resources\ReportTemplates\Pages\CreateReportTemplate;
use App\Models\Client;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('un usuario interno puede ver el listado de plantillas de reporte', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    ReportTemplate::factory()->count(2)->create();

    $this->actingAs($admin)
        ->get('/admin/report-templates')
        ->assertSuccessful();
});

test('el formulario de creacion carga para un usuario interno', function () {
    $analyst = User::factory()->create(['role' => UserRole::Analyst]);

    $this->actingAs($analyst)
        ->get('/admin/report-templates/create')
        ->assertSuccessful();
});

test('crea una plantilla global con secciones y branding_overrides', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test(CreateReportTemplate::class)
        ->fillForm([
            'name' => 'Plantilla mensual estándar',
            'client_id' => null,
            'sections' => [ReportSection::ExecutiveSummary->value, ReportSection::PositionsTable->value],
            'branding_overrides' => ['primary_color' => '#ff5500'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = ReportTemplate::query()->where('name', 'Plantilla mensual estándar')->first();

    expect($template)->not->toBeNull()
        ->and($template->client_id)->toBeNull()
        ->and($template->sections)->toBe([ReportSection::ExecutiveSummary->value, ReportSection::PositionsTable->value])
        ->and($template->branding_overrides['primary_color'])->toBe('#ff5500');
});

test('crea una plantilla propia de un cliente', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $client = Client::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateReportTemplate::class)
        ->fillForm([
            'name' => 'Plantilla del cliente',
            'client_id' => $client->id,
            'sections' => [ReportSection::ExecutiveSummary->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = ReportTemplate::query()->where('name', 'Plantilla del cliente')->first();

    expect($template->client_id)->toBe($client->id);
});

test('requiere al menos una seccion', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Livewire::actingAs($admin)
        ->test(CreateReportTemplate::class)
        ->fillForm([
            'name' => 'Sin secciones',
            'sections' => [],
        ])
        ->call('create')
        ->assertHasFormErrors(['sections']);
});
