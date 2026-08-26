<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use App\Jobs\SendReportEmail;
use App\Mail\ReportGeneratedMail;
use App\Models\Client;
use App\Models\Project;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('envia el correo al contacto del cliente y marca sent_at', function () {
    Mail::fake();
    Storage::fake('local');

    $client = Client::factory()->create(['contact_email' => 'contacto@cliente.com']);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $template = ReportTemplate::factory()->create();

    $report = Report::factory()->create([
        'project_id' => $project->id,
        'template_id' => $template->id,
        'status' => ReportStatus::Completed,
        'file_path' => 'reports/'.$project->id.'/report-test.pdf',
    ]);
    Storage::disk('local')->put($report->file_path, '%PDF-1.4 contenido de prueba');

    (new SendReportEmail($report->id))->handle();

    Mail::assertSent(ReportGeneratedMail::class, fn (ReportGeneratedMail $mail) => $mail->hasTo('contacto@cliente.com') && $mail->report->is($report));

    expect($report->fresh()->sent_at)->not->toBeNull();
});

test('no envia nada si el reporte no esta completado', function () {
    Mail::fake();
    Log::spy();

    $report = Report::factory()->create(['status' => ReportStatus::Pending, 'file_path' => null]);

    (new SendReportEmail($report->id))->handle();

    Mail::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
    expect($report->fresh()->sent_at)->toBeNull();
});

test('no envia nada si al cliente le falta contact_email', function () {
    Mail::fake();
    Log::spy();
    Storage::fake('local');

    $client = Client::factory()->create(['contact_email' => null]);
    $project = Project::factory()->create(['client_id' => $client->id]);
    $report = Report::factory()->create([
        'project_id' => $project->id,
        'status' => ReportStatus::Completed,
        'file_path' => 'reports/'.$project->id.'/report-test.pdf',
    ]);
    Storage::disk('local')->put($report->file_path, '%PDF-1.4 contenido de prueba');

    (new SendReportEmail($report->id))->handle();

    Mail::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
});
