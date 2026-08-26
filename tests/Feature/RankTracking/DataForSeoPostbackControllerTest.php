<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ProcessDataForSeoPostback;
use App\Models\DataForSeoTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['rank_tracking.webhook_token' => 'test-token']);
});

function postbackUrl(string $token = 'test-token'): string
{
    return '/webhooks/dataforseo/serp?token='.$token;
}

test('rechaza con 403 si el token no coincide', function () {
    $response = $this->call('POST', postbackUrl('token-incorrecto'), content: json_encode(['tasks' => [['id' => 'x']]]));

    $response->assertStatus(403);
});

test('devuelve 400 si el body no es JSON valido', function () {
    $response = $this->call('POST', postbackUrl(), content: 'esto no es json {{{');

    $response->assertStatus(400)->assertJson(['status' => 'invalid_payload']);
});

test('devuelve 400 si falta el task id en el payload', function () {
    $response = $this->call('POST', postbackUrl(), content: json_encode(['tasks' => [['status_code' => 20000]]]));

    $response->assertStatus(400)->assertJson(['status' => 'missing_task_id']);
});

test('devuelve 404 si el task_id no corresponde a ninguna tarea nuestra', function () {
    Bus::fake();

    $response = $this->call('POST', postbackUrl(), content: json_encode(['tasks' => [['id' => 'inexistente']]]));

    $response->assertStatus(404)->assertJson(['status' => 'unknown_task']);
    Bus::assertNotDispatched(ProcessDataForSeoPostback::class);
});

test('es idempotente: una tarea que ya no esta pending responde already_processed sin despachar el job', function () {
    Bus::fake();

    $task = DataForSeoTask::factory()->create(['task_id' => 'task-completada', 'status' => DataForSeoTaskStatus::Completed]);

    $response = $this->call('POST', postbackUrl(), content: json_encode(['tasks' => [['id' => $task->task_id]]]));

    $response->assertOk()->assertJson(['status' => 'already_processed']);
    Bus::assertNotDispatched(ProcessDataForSeoPostback::class);
});

test('acepta un payload JSON plano, guarda el payload crudo y despacha el job de procesamiento', function () {
    Bus::fake();

    $task = DataForSeoTask::factory()->create(['task_id' => 'task-pendiente', 'status' => DataForSeoTaskStatus::Pending]);

    $payload = ['tasks' => [['id' => 'task-pendiente', 'status_code' => 20000]]];
    $response = $this->call('POST', postbackUrl(), content: json_encode($payload));

    $response->assertOk()->assertJson(['status' => 'accepted']);

    expect(json_decode($task->fresh()->payload_received, true))->toBe($payload);

    Bus::assertDispatched(ProcessDataForSeoPostback::class, fn (ProcessDataForSeoPostback $job) => $job->dataForSeoTaskId === $task->id);
});

test('acepta un payload comprimido en gzip detectado por los magic bytes', function () {
    Bus::fake();

    $task = DataForSeoTask::factory()->create(['task_id' => 'task-gzip', 'status' => DataForSeoTaskStatus::Pending]);

    $payload = ['tasks' => [['id' => 'task-gzip', 'status_code' => 20000]]];
    $gzipped = gzencode(json_encode($payload));

    $response = $this->call('POST', postbackUrl(), content: $gzipped);

    $response->assertOk()->assertJson(['status' => 'accepted']);
    expect(json_decode($task->fresh()->payload_received, true))->toBe($payload);
    Bus::assertDispatched(ProcessDataForSeoPostback::class, fn (ProcessDataForSeoPostback $job) => $job->dataForSeoTaskId === $task->id);
});
