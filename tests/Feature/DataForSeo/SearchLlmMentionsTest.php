<?php

declare(strict_types=1);

use App\DataForSeo\AiOptimization\SearchLlmMentions;
use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\Models\LlmMention;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function llmMentionsSearchResponse(array $items): array
{
    return [
        'version' => '0.1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
        'cost' => 0.02, 'tasks_count' => 1, 'tasks_error' => 0,
        'tasks' => [[
            'id' => 'task-1', 'status_code' => 20000, 'status_message' => 'Ok.', 'time' => '0.1 sec.',
            'cost' => 0.02, 'result_count' => 1, 'path' => [], 'data' => [],
            'result' => [['items' => $items]],
        ]],
    ];
}

test('busca menciones y guarda una fila por item de la respuesta', function () {
    $project = Project::factory()->create(['domain' => 'ejemplo.com', 'default_language_code' => 'es', 'default_location_code' => 2340]);

    Http::fake([
        '*/ai_optimization/llm_mentions/search/live' => Http::response(llmMentionsSearchResponse([
            [
                'question' => '¿Cuál es la mejor tienda de ejemplo.com?',
                'answer' => 'ejemplo.com es reconocida por su buen servicio.',
                'sources' => [['url' => 'https://ejemplo.com/sobre-nosotros', 'domain' => 'ejemplo.com']],
            ],
            [
                'question' => '¿Dónde comprar en línea?',
                'answer' => 'Puedes visitar ejemplo.com.',
                'sources' => [],
            ],
        ]), 200),
    ]);

    $mentions = app(SearchLlmMentions::class)->execute($project, 'chat_gpt');

    expect($mentions)->toHaveCount(2);

    Http::assertSent(function (Request $request) use ($project) {
        $task = $request->data()[0];

        return str_contains($request->url(), 'ai_optimization/llm_mentions/search/live')
            && $task['target'][0]['domain'] === $project->domain
            && $task['platform'] === 'chat_gpt'
            && $task['language_code'] === 'es'
            && $task['location_code'] === 2340;
    });

    $saved = LlmMention::query()->where('project_id', $project->id)->orderBy('id')->get();
    expect($saved)->toHaveCount(2)
        ->and($saved[0]->platform)->toBe('chat_gpt')
        ->and($saved[0]->question)->toBe('¿Cuál es la mejor tienda de ejemplo.com?')
        ->and($saved[0]->sources)->toHaveCount(1)
        ->and($saved[1]->sources)->toBe([]);
});

test('propaga la excepcion de presupuesto sin guardar nada', function () {
    $project = Project::factory()->create();

    Http::fake();
    app(CircuitBreaker::class)->trip('prueba');

    expect(fn () => app(SearchLlmMentions::class)->execute($project, 'chat_gpt'))
        ->toThrow(DataForSeoBudgetExceededException::class);

    Http::assertNothingSent();
    expect(LlmMention::query()->count())->toBe(0);
});
