<?php

declare(strict_types=1);

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('registra un evento con el usuario, ip y user agent de la request actual', function () {
    $user = User::factory()->create();

    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '203.0.113.10', 'HTTP_USER_AGENT' => 'Prueba/1.0']);
    $this->app->instance('request', $request);
    $this->app->instance(Request::class, $request);

    app(AuditLogger::class)->log(AuditEvent::ReportDownloaded, user: $user, context: ['report_id' => 5]);

    $log = AuditLog::query()->latest('id')->first();

    expect($log->event)->toBe(AuditEvent::ReportDownloaded)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->email)->toBe($user->email)
        ->and($log->ip_address)->toBe('203.0.113.10')
        ->and($log->user_agent)->toBe('Prueba/1.0')
        ->and($log->context)->toBe(['report_id' => 5]);
});

test('un contexto vacio se guarda como null, no como arreglo vacio', function () {
    app(AuditLogger::class)->log(AuditEvent::Logout, user: User::factory()->create());

    expect(AuditLog::query()->latest('id')->first()->context)->toBeNull();
});

test('acepta un email explicito sin usuario resuelto (login fallido)', function () {
    app(AuditLogger::class)->log(AuditEvent::LoginFailed, email: 'nadie@existe.com');

    $log = AuditLog::query()->latest('id')->first();

    expect($log->user_id)->toBeNull()
        ->and($log->email)->toBe('nadie@existe.com');
});
