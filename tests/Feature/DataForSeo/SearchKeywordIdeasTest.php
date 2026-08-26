<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\Labs\SearchKeywordIdeas;
use App\Models\KeywordIdea;
use App\Models\KeywordResearchSession;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('crea una sesion y sus ideas a partir de la respuesta de keyword_ideas', function () {
    $project = Project::factory()->create(['default_location_code' => 2340, 'default_language_code' => 'es']);
    $user = User::factory()->create();

    Http::fake([
        '*/dataforseo_labs/google/keyword_ideas/live' => Http::response([
            'version' => '0.1.20250101',
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.3 sec.',
            'cost' => 0.05,
            'tasks_count' => 1,
            'tasks_error' => 0,
            'tasks' => [
                [
                    'id' => 'task-1',
                    'status_code' => 20000,
                    'status_message' => 'Ok.',
                    'time' => '0.3 sec.',
                    'cost' => 0.05,
                    'result_count' => 1,
                    'path' => ['v3', 'dataforseo_labs', 'google', 'keyword_ideas', 'live'],
                    'data' => [],
                    'result' => [
                        [
                            'seed_keywords' => ['zapatos deportivos'],
                            'total_count' => 2,
                            'items_count' => 2,
                            'items' => [
                                [
                                    'keyword' => 'zapatos deportivos hombre',
                                    'keyword_info' => ['search_volume' => 880, 'cpc' => 0.35, 'competition' => 0.4],
                                    'keyword_properties' => ['keyword_difficulty' => 42],
                                    'search_intent_info' => ['main_intent' => 'commercial'],
                                ],
                                [
                                    'keyword' => 'zapatos deportivos mujer',
                                    'keyword_info' => ['search_volume' => 590, 'cpc' => 0.28, 'competition' => 0.3],
                                    'keyword_properties' => ['keyword_difficulty' => 35],
                                    'search_intent_info' => ['main_intent' => 'commercial'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], 200),
    ]);

    $session = app(SearchKeywordIdeas::class)->execute($project, $user, 'zapatos deportivos');

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request) {
        $payload = $request->data()[0];

        return $payload['keywords'] === ['zapatos deportivos']
            && $payload['location_code'] === 2340
            && $payload['language_code'] === 'es';
    });

    expect($session->exists)->toBeTrue()
        ->and($session->project_id)->toBe($project->id)
        ->and($session->user_id)->toBe($user->id)
        ->and($session->seed_keyword)->toBe('zapatos deportivos')
        ->and($session->source_endpoint)->toBe('dataforseo_labs/google/keyword_ideas/live')
        ->and((float) $session->cost)->toEqual(0.05);

    expect(KeywordResearchSession::query()->count())->toBe(1)
        ->and(KeywordIdea::query()->where('session_id', $session->id)->count())->toBe(2);

    $idea = KeywordIdea::query()->where('keyword', 'zapatos deportivos hombre')->first();

    expect($idea->search_volume)->toBe(880)
        ->and((float) $idea->cpc)->toEqual(0.35)
        ->and((float) $idea->competition)->toEqual(0.40)
        ->and($idea->difficulty)->toBe(42)
        ->and($idea->intent)->toBe('commercial')
        ->and($idea->is_selected)->toBeFalse();
});

test('no crea ideas si items viene vacio, pero registra la sesion con su costo', function () {
    $project = Project::factory()->create();

    Http::fake([
        '*/dataforseo_labs/google/keyword_ideas/live' => Http::response([
            'version' => '0.1.20250101', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.01, 'result_count' => 0, 'path' => [], 'data' => [],
                'result' => [['seed_keywords' => ['xyz'], 'total_count' => 0, 'items_count' => 0, 'items' => []]],
            ]],
        ], 200),
    ]);

    $session = app(SearchKeywordIdeas::class)->execute($project, null, 'xyz');

    expect((float) $session->cost)->toEqual(0.01)
        ->and($session->user_id)->toBeNull()
        ->and($session->keywordIdeas()->count())->toBe(0);
});

test('ignora items sin keyword valida', function () {
    $project = Project::factory()->create();

    Http::fake([
        '*/dataforseo_labs/google/keyword_ideas/live' => Http::response([
            'version' => '0.1.20250101', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.01, 'tasks_count' => 1, 'tasks_error' => 0,
            'tasks' => [[
                'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
                'cost' => 0.01, 'result_count' => 1, 'path' => [], 'data' => [],
                'result' => [['seed_keywords' => ['xyz'], 'total_count' => 1, 'items_count' => 1, 'items' => [
                    ['keyword' => null, 'keyword_info' => ['search_volume' => 10]],
                    ['keyword' => '', 'keyword_info' => ['search_volume' => 20]],
                ]]],
            ]],
        ], 200),
    ]);

    $session = app(SearchKeywordIdeas::class)->execute($project, null, 'xyz');

    expect($session->keywordIdeas()->count())->toBe(0);
});

test('propaga la excepcion de presupuesto sin crear sesion ni ideas', function () {
    $project = Project::factory()->create();

    Http::fake();
    app(CircuitBreaker::class)->trip('prueba');

    expect(fn () => app(SearchKeywordIdeas::class)->execute($project, null, 'zapatos'))
        ->toThrow(DataForSeoBudgetExceededException::class);

    Http::assertNothingSent();
    expect(KeywordResearchSession::query()->count())->toBe(0);
});
