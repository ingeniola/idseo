<?php

declare(strict_types=1);

use App\DataForSeo\DataForSeoClient;
use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\DataForSeo\Exceptions\DataForSeoPermanentException;
use App\DataForSeo\Exceptions\DataForSeoTransientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Spatie\LaravelData\Exceptions\CannotCreateData;

function dataForSeoFixture(string $name): array
{
    return json_decode(
        file_get_contents(base_path("tests/Fixtures/dataforseo/{$name}.json")),
        associative: true,
    );
}

beforeEach(function () {
    Sleep::fake();
});

test('un request exitoso devuelve un DataForSeoResponseData con las tareas parseadas', function () {
    Event::fake();
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(dataForSeoFixture('task_post_success'), 200),
    ]);

    $response = app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
        ['keyword' => 'zapatos deportivos', 'location_code' => 2340, 'language_code' => 'es'],
    ]);

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->statusCode)->toBe(20000)
        ->and($response->tasksCount)->toBe(1)
        ->and($response->tasks)->toHaveCount(1)
        ->and($response->tasks[0]->id)->toBe('08251234-1535-0066-0000-9c1a2f3e4b5c');

    Http::assertSentCount(1);
    Event::assertDispatched(DataForSeoRequestCompleted::class);
});

test('un error transitorio (HTTP 500) se reintenta hasta agotar los intentos y luego lanza excepción', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response('Internal Server Error', 500),
    ]);

    app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
        ['keyword' => 'zapatos deportivos'],
    ]);
})->throws(DataForSeoTransientException::class);

test('un error transitorio reintenta 3 veces antes de fallar (4 requests en total)', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response('Internal Server Error', 500),
    ]);

    try {
        app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
            ['keyword' => 'zapatos deportivos'],
        ]);
    } catch (DataForSeoTransientException) {
        // esperado
    }

    Http::assertSentCount(4);
});

test('un status_code transitorio de DataForSEO (50000) dentro de un HTTP 200 también se reintenta y falla', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(dataForSeoFixture('error_transient'), 200),
    ]);

    try {
        app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
            ['keyword' => 'zapatos deportivos'],
        ]);
    } catch (DataForSeoTransientException) {
        // esperado
    }

    Http::assertSentCount(4);
});

test('un error permanente (HTTP 400) no se reintenta', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response('Bad Request', 400),
    ]);

    try {
        app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
            ['keyword' => ''],
        ]);
    } catch (DataForSeoPermanentException) {
        // esperado
    }

    Http::assertSentCount(1);
});

test('un status_code permanente de DataForSEO (40501) dentro de un HTTP 200 no se reintenta', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(dataForSeoFixture('error_permanent'), 200),
    ]);

    try {
        app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
            ['keyword' => ''],
        ]);
    } catch (DataForSeoPermanentException $exception) {
        expect($exception->apiStatusCode)->toBe(40501);
    }

    Http::assertSentCount(1);
});

test('una respuesta malformada (sin los campos requeridos del sobre) lanza una excepción', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::response(dataForSeoFixture('malformed'), 200),
    ]);

    app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
        ['keyword' => 'zapatos deportivos'],
    ]);
})->throws(CannotCreateData::class);

test('un timeout de conexión se reintenta hasta agotar los intentos', function () {
    Http::fake([
        '*/serp/google/organic/task_post' => Http::failedConnection(),
    ]);

    try {
        app(DataForSeoClient::class)->post('serp/google/organic/task_post', [
            ['keyword' => 'zapatos deportivos'],
        ]);
    } catch (ConnectionException) {
        // esperado
    }

    Http::assertSentCount(4);
});

test('post() rechaza lotes que exceden el máximo de tareas por request', function () {
    $tasks = array_fill(0, 101, ['keyword' => 'zapatos deportivos']);

    app(DataForSeoClient::class)->post('serp/google/organic/task_post', $tasks);
})->throws(InvalidArgumentException::class);

test('get() no manda body y usa autenticación básica', function () {
    Http::fake([
        '*/appendix/user_data' => Http::response([
            'version' => '0.1.20250101',
            'status_code' => 20000,
            'status_message' => 'Ok.',
            'time' => '0.01 sec.',
            'cost' => 0,
            'tasks_count' => 1,
            'tasks_error' => 0,
            'tasks' => [],
        ], 200),
    ]);

    app(DataForSeoClient::class)->get('appendix/user_data');

    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && $request->hasHeader('Authorization');
    });
});
