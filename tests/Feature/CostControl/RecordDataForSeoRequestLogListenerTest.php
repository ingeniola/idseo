<?php

declare(strict_types=1);

use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\Listeners\RecordDataForSeoRequestLog;
use App\Models\DataForSeoRequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registra un renglon en dataforseo_requests para una respuesta exitosa', function () {
    $event = new DataForSeoRequestCompleted(
        method: 'POST',
        endpoint: 'serp/google/organic/task_post',
        httpStatus: 200,
        durationMs: 120,
        apiStatusCode: 20000,
        cost: 0.0025,
    );

    (new RecordDataForSeoRequestLog)->handle($event);

    expect(DataForSeoRequestLog::query()->count())->toBe(1);

    $entry = DataForSeoRequestLog::query()->first();

    expect($entry->method)->toBe('POST')
        ->and($entry->endpoint)->toBe('serp/google/organic/task_post')
        ->and($entry->http_status)->toBe(200)
        ->and($entry->api_status_code)->toBe(20000);
});

test('registra un renglon incluso cuando fue un fallo de conexion puro (sin excepcion)', function () {
    $event = new DataForSeoRequestCompleted(
        method: 'POST',
        endpoint: 'serp/google/organic/task_post',
        httpStatus: null,
        durationMs: 30000,
        apiStatusCode: null,
        cost: null,
    );

    (new RecordDataForSeoRequestLog)->handle($event);

    $entry = DataForSeoRequestLog::query()->first();

    expect($entry->http_status)->toBeNull()
        ->and($entry->api_status_code)->toBeNull()
        ->and($entry->cost)->toBeNull();
});
