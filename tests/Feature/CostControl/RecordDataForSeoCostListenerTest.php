<?php

declare(strict_types=1);

use App\DataForSeo\Enums\EndpointGroup;
use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\Listeners\RecordDataForSeoCost;
use App\Models\Client;
use App\Models\CostLedger;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registra un renglon en cost_ledger cuando hay api_status_code', function () {
    $event = new DataForSeoRequestCompleted(
        method: 'POST',
        endpoint: 'serp/google/organic/task_post',
        httpStatus: 200,
        durationMs: 120,
        apiStatusCode: 20000,
        cost: 0.0025,
    );

    (new RecordDataForSeoCost)->handle($event);

    expect(CostLedger::query()->count())->toBe(1);

    $entry = CostLedger::query()->first();

    expect($entry->endpoint_group)->toBe(EndpointGroup::Serp)
        ->and((float) $entry->cost)->toEqual(0.0025)
        ->and($entry->client_id)->toBeNull()
        ->and($entry->project_id)->toBeNull();
});

test('no registra nada cuando no hay api_status_code (fallo de transporte puro)', function () {
    $event = new DataForSeoRequestCompleted(
        method: 'POST',
        endpoint: 'serp/google/organic/task_post',
        httpStatus: null,
        durationMs: 30000,
        apiStatusCode: null,
        cost: null,
    );

    (new RecordDataForSeoCost)->handle($event);

    expect(CostLedger::query()->count())->toBe(0);
});

test('resuelve client_id a partir del project_id del evento', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);

    $event = new DataForSeoRequestCompleted(
        method: 'POST',
        endpoint: 'serp/google/organic/task_post',
        httpStatus: 200,
        durationMs: 120,
        apiStatusCode: 20000,
        cost: 0.0025,
        projectId: $project->id,
    );

    (new RecordDataForSeoCost)->handle($event);

    $entry = CostLedger::query()->first();
    expect($entry->project_id)->toBe($project->id)
        ->and($entry->client_id)->toBe($client->id);
});

test('registra costo 0 cuando la tarea todavia no tiene resultados', function () {
    $event = new DataForSeoRequestCompleted(
        method: 'POST',
        endpoint: 'keywords_data/google_ads/search_volume/task_post',
        httpStatus: 200,
        durationMs: 80,
        apiStatusCode: 20100,
        cost: 0.0,
    );

    (new RecordDataForSeoCost)->handle($event);

    $entry = CostLedger::query()->first();

    expect((float) $entry->cost)->toEqual(0.0)
        ->and($entry->endpoint_group)->toBe(EndpointGroup::KeywordsData);
});
