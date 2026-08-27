<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\KeywordData\EnrichKeywordVolumes;
use App\Models\Keyword;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('agrupa keywords por ubicacion e idioma en una sola llamada y actualiza el volumen', function () {
    $project = Project::factory()->create();

    $hn1 = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'zapatos deportivos', 'location_code' => 2340, 'language_code' => 'es']);
    $hn2 = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'zapatos para correr', 'location_code' => 2340, 'language_code' => 'es']);
    $us1 = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'running shoes', 'location_code' => 2840, 'language_code' => 'en']);

    Http::fake([
        '*/keywords_data/google_ads/search_volume/live' => Http::response([
            'version' => '0.1.20250101',
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.2 sec.',
            'cost' => 0.05,
            'tasks_count' => 2,
            'tasks_error' => 0,
            'tasks' => [
                [
                    'id' => 'task-1',
                    'status_code' => 20000,
                    'status_message' => 'Ok.',
                    'time' => '0.1 sec.',
                    'cost' => 0.025,
                    'result_count' => 2,
                    'path' => ['v3', 'keywords_data', 'google_ads', 'search_volume', 'live'],
                    'data' => [],
                    'result' => [
                        ['keyword' => 'zapatos deportivos', 'location_code' => 2340, 'language_code' => 'es', 'search_volume' => 1200, 'cpc' => 0.45, 'competition_index' => 40],
                        ['keyword' => 'zapatos para correr', 'location_code' => 2340, 'language_code' => 'es', 'search_volume' => 300, 'cpc' => 0.30, 'competition_index' => 20],
                    ],
                ],
                [
                    'id' => 'task-2',
                    'status_code' => 20000,
                    'status_message' => 'Ok.',
                    'time' => '0.1 sec.',
                    'cost' => 0.025,
                    'result_count' => 1,
                    'path' => ['v3', 'keywords_data', 'google_ads', 'search_volume', 'live'],
                    'data' => [],
                    'result' => [
                        ['keyword' => 'running shoes', 'location_code' => 2840, 'language_code' => 'en', 'search_volume' => 5000, 'cpc' => 1.2, 'competition_index' => 80],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = app(EnrichKeywordVolumes::class)->execute($project->keywords()->get());

    Http::assertSentCount(1);

    $sentPayload = null;
    Http::assertSent(function (Request $request) use (&$sentPayload) {
        $sentPayload = $request->data();

        return true;
    });

    expect($sentPayload)->toHaveCount(2);

    expect($result->requested)->toBe(3)
        ->and($result->updated)->toBe(3);

    expect($hn1->fresh()->search_volume)->toBe(1200)
        ->and((float) $hn1->fresh()->cpc)->toEqual(0.45)
        ->and((float) $hn1->fresh()->competition)->toEqual(0.40)
        ->and($hn1->fresh()->volume_updated_at)->not->toBeNull();

    expect($us1->fresh()->search_volume)->toBe(5000);
});

test('una tarea con status_code de error no actualiza esas keywords y queda reportada en failures', function () {
    $project = Project::factory()->create();

    $bad = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'agencia seo en honduras', 'location_code' => 2340, 'language_code' => 'es-419', 'search_volume' => null]);
    $good = Keyword::factory()->create(['project_id' => $project->id, 'keyword' => 'running shoes', 'location_code' => 2840, 'language_code' => 'en']);

    Http::fake([
        '*/keywords_data/google_ads/search_volume/live' => Http::response([
            'version' => '0.1.20250101',
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.2 sec.',
            'cost' => 0.025,
            'tasks_count' => 2,
            'tasks_error' => 1,
            'tasks' => [
                [
                    'id' => 'task-1',
                    'status_code' => 40501,
                    'status_message' => "Invalid Field: 'language_code'.",
                    'time' => '0.05 sec.',
                    'cost' => 0.0,
                    'result_count' => 0,
                    'path' => ['v3', 'keywords_data', 'google_ads', 'search_volume', 'live'],
                    'data' => [],
                    'result' => null,
                ],
                [
                    'id' => 'task-2',
                    'status_code' => 20000,
                    'status_message' => 'Ok.',
                    'time' => '0.1 sec.',
                    'cost' => 0.025,
                    'result_count' => 1,
                    'path' => ['v3', 'keywords_data', 'google_ads', 'search_volume', 'live'],
                    'data' => [],
                    'result' => [
                        ['keyword' => 'running shoes', 'location_code' => 2840, 'language_code' => 'en', 'search_volume' => 5000, 'cpc' => 1.2, 'competition_index' => 80],
                    ],
                ],
            ],
        ], 200),
    ]);

    $result = app(EnrichKeywordVolumes::class)->execute($project->keywords()->get());

    expect($result->requested)->toBe(2)
        ->and($result->updated)->toBe(1)
        ->and($result->failures)->toBe(["Invalid Field: 'language_code'."]);

    expect($bad->fresh()->search_volume)->toBeNull()
        ->and($good->fresh()->search_volume)->toBe(5000);
});

test('no manda nada y devuelve cero si la coleccion esta vacia', function () {
    Http::fake();

    $result = app(EnrichKeywordVolumes::class)->execute(new Collection);

    Http::assertNothingSent();
    expect($result->requested)->toBe(0)
        ->and($result->updated)->toBe(0);
});

test('propaga la excepcion de presupuesto sin actualizar nada', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id, 'search_volume' => 999]);

    Http::fake();
    app(CircuitBreaker::class)->trip('prueba');

    expect(fn () => app(EnrichKeywordVolumes::class)->execute($project->keywords()->get()))
        ->toThrow(DataForSeoBudgetExceededException::class);

    Http::assertNothingSent();
    expect($keyword->fresh()->search_volume)->toBe(999);
});
