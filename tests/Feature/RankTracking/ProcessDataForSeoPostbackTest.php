<?php

declare(strict_types=1);

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use App\Jobs\ProcessDataForSeoPostback;
use App\Models\DataForSeoTask;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\Ranking;
use App\Models\SerpSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function serpTaskResultPayload(array $items, int $statusCode = 20000, float $cost = 0.0025): array
{
    return [
        'tasks' => [[
            'id' => 'task-serp-1',
            'status_code' => $statusCode,
            'status_message' => $statusCode === 20000 ? 'Ok.' : 'Error.',
            'cost' => $cost,
            'result' => $statusCode === 20000 ? [[
                'items' => $items,
            ]] : null,
        ]],
    ];
}

function pendingSerpTask(Keyword $keyword, array $payload): DataForSeoTask
{
    return DataForSeoTask::factory()->create([
        'task_id' => 'task-serp-1',
        'endpoint' => 'serp/google/organic/task_post',
        'taskable_type' => Keyword::class,
        'taskable_id' => $keyword->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode($payload),
    ]);
}

test('crea el ranking y el snapshot cuando el postback trae una posicion organica', function () {
    $project = Project::factory()->create(['domain' => 'miempresa.com']);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $items = [
        ['type' => 'organic', 'domain' => 'miempresa.com', 'rank_group' => 4, 'url' => 'https://miempresa.com/pagina'],
        ['type' => 'featured_snippet', 'domain' => 'otro.com'],
    ];
    $task = pendingSerpTask($keyword, serpTaskResultPayload($items));

    (new ProcessDataForSeoPostback($task->id))->handle();

    $task->refresh();
    expect($task->status)->toBe(DataForSeoTaskStatus::Completed)
        ->and((float) $task->cost)->toEqual(0.0025);

    $ranking = Ranking::query()->where('keyword_id', $keyword->id)->first();
    expect($ranking)->not->toBeNull()
        ->and($ranking->position)->toBe(4)
        ->and($ranking->url)->toBe('https://miempresa.com/pagina')
        ->and($ranking->previous_position)->toBeNull()
        ->and($ranking->is_featured_snippet)->toBeTrue()
        ->and($ranking->serp_features)->toBe(['featured_snippet'])
        ->and($ranking->checked_at)->toBe(Carbon::now()->toDateString());

    $snapshot = SerpSnapshot::query()->where('keyword_id', $keyword->id)->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->top_results)->toHaveCount(1);
});

test('guarda previous_position con la ultima posicion conocida antes del nuevo ranking', function () {
    $project = Project::factory()->create(['domain' => 'miempresa.com']);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => Carbon::yesterday()->toDateString(), 'position' => 9]);

    $items = [
        ['type' => 'organic', 'domain' => 'miempresa.com', 'rank_group' => 6, 'url' => 'https://miempresa.com'],
    ];
    $task = pendingSerpTask($keyword, serpTaskResultPayload($items));

    (new ProcessDataForSeoPostback($task->id))->handle();

    $ranking = Ranking::query()->where('keyword_id', $keyword->id)->where('checked_at', Carbon::today()->toDateString())->first();
    expect($ranking->position)->toBe(6)
        ->and($ranking->previous_position)->toBe(9);
});

test('guarda position null cuando el dominio no aparece en los resultados', function () {
    $project = Project::factory()->create(['domain' => 'miempresa.com']);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $items = [
        ['type' => 'organic', 'domain' => 'otro.com', 'rank_group' => 1, 'url' => 'https://otro.com'],
    ];
    $task = pendingSerpTask($keyword, serpTaskResultPayload($items));

    (new ProcessDataForSeoPostback($task->id))->handle();

    $ranking = Ranking::query()->where('keyword_id', $keyword->id)->first();
    expect($ranking->position)->toBeNull()
        ->and($task->fresh()->status)->toBe(DataForSeoTaskStatus::Completed);
});

test('marca la tarea como failed si el status_code de la tarea no es exitoso', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $task = pendingSerpTask($keyword, serpTaskResultPayload([], 40501));

    (new ProcessDataForSeoPostback($task->id))->handle();

    $task->refresh();
    expect($task->status)->toBe(DataForSeoTaskStatus::Failed)
        ->and($task->error_message)->not->toBeNull();

    expect(Ranking::query()->count())->toBe(0);
});

test('no hace nada si la tarea ya no esta pending (idempotencia)', function () {
    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $task = pendingSerpTask($keyword, serpTaskResultPayload([]));
    $task->update(['status' => DataForSeoTaskStatus::Completed]);

    (new ProcessDataForSeoPostback($task->id))->handle();

    expect(Ranking::query()->count())->toBe(0)
        ->and(SerpSnapshot::query()->count())->toBe(0);
});

test('procesar el mismo postback dos veces no duplica el ranking del dia (updateOrCreate)', function () {
    $project = Project::factory()->create(['domain' => 'miempresa.com']);
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);

    $items = [
        ['type' => 'organic', 'domain' => 'miempresa.com', 'rank_group' => 2, 'url' => 'https://miempresa.com'],
    ];
    $task = pendingSerpTask($keyword, serpTaskResultPayload($items));

    (new ProcessDataForSeoPostback($task->id))->handle();

    // Simula un reintento del job sobre la misma tarea antes de que
    // termine de marcarse como Completed no aplica aquí (ya quedó
    // Completed), así que se fuerza de nuevo a Pending para ejercer el
    // path de updateOrCreate directamente.
    $task->update(['status' => DataForSeoTaskStatus::Pending]);
    (new ProcessDataForSeoPostback($task->id))->handle();

    expect(Ranking::query()->where('keyword_id', $keyword->id)->count())->toBe(1);
});

test('registra error si la tarea del postback no corresponde a una Keyword', function () {
    $project = Project::factory()->create();

    $task = DataForSeoTask::factory()->create([
        'task_id' => 'task-otro-tipo',
        'taskable_type' => Project::class,
        'taskable_id' => $project->id,
        'status' => DataForSeoTaskStatus::Pending,
        'payload_received' => json_encode(serpTaskResultPayload([])),
    ]);

    (new ProcessDataForSeoPostback($task->id))->handle();

    expect($task->fresh()->status)->toBe(DataForSeoTaskStatus::Pending);
});
