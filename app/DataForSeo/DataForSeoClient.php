<?php

declare(strict_types=1);

namespace App\DataForSeo;

use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Data\DataForSeoResponseData;
use App\DataForSeo\Events\DataForSeoRequestCompleted;
use App\DataForSeo\Exceptions\DataForSeoBudgetExceededException;
use App\DataForSeo\Exceptions\DataForSeoPermanentException;
use App\DataForSeo\Exceptions\DataForSeoTransientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Sleep;
use Throwable;

/**
 * Cliente HTTP de la API v3 de DataForSEO: autenticación, rate limiting
 * propio, reintentos con backoff exponencial y traducción de errores a
 * excepciones tipadas. No sabe nada de endpoints específicos: eso vive
 * en clases "por servicio" de fases posteriores, que reciben esta clase
 * inyectada y le piden post()/get().
 */
class DataForSeoClient
{
    private const RETRY_DELAYS_MS = [1_000, 5_000, 25_000];

    private const RATE_LIMIT_KEY = 'dataforseo:http';

    public function __construct(
        private readonly string $login,
        private readonly string $password,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $rateLimitPerMinute,
        private readonly int $maxTasksPerRequest,
        private readonly CircuitBreaker $circuitBreaker,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $tasks
     */
    public function post(string $endpoint, array $tasks): DataForSeoResponseData
    {
        if (count($tasks) > $this->maxTasksPerRequest) {
            throw new \InvalidArgumentException(sprintf(
                'No se pueden enviar %d tareas en un solo request; el máximo es %d. Agrupa en varios requests.',
                count($tasks),
                $this->maxTasksPerRequest,
            ));
        }

        if ($this->circuitBreaker->checkGlobalBudget()) {
            throw new DataForSeoBudgetExceededException(
                $this->circuitBreaker->reason() ?? 'El circuit breaker de presupuesto está activo.',
            );
        }

        return $this->send('POST', $endpoint, $tasks);
    }

    public function get(string $endpoint): DataForSeoResponseData
    {
        return $this->send('GET', $endpoint);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $payload
     */
    private function send(string $method, string $endpoint, ?array $payload = null): DataForSeoResponseData
    {
        $this->throttle();

        return retry(
            times: self::RETRY_DELAYS_MS,
            callback: function () use ($method, $endpoint, $payload) {
                $startedAt = microtime(true);

                try {
                    $response = Http::baseUrl($this->baseUrl)
                        ->withBasicAuth($this->login, $this->password)
                        ->timeout($this->timeout)
                        ->send($method, $endpoint, $payload === null ? [] : ['json' => $payload]);
                } catch (ConnectionException $exception) {
                    Log::warning('dataforseo.connection_failed', [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'message' => $exception->getMessage(),
                    ]);

                    throw $exception;
                }

                $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

                return $this->handleResponse($method, $endpoint, $response, $durationMs);
            },
            when: fn (Throwable $exception) => $exception instanceof ConnectionException
                || $exception instanceof DataForSeoTransientException,
        );
    }

    private function handleResponse(string $method, string $endpoint, Response $response, int $durationMs): DataForSeoResponseData
    {
        if ($response->status() === 429 || $response->serverError()) {
            $this->record($method, $endpoint, $response->status(), $durationMs, null, null);

            throw new DataForSeoTransientException(
                "DataForSEO respondió HTTP {$response->status()} en {$endpoint}.",
                httpStatus: $response->status(),
            );
        }

        if ($response->clientError()) {
            $this->record($method, $endpoint, $response->status(), $durationMs, null, null);

            throw new DataForSeoPermanentException(
                "DataForSEO respondió HTTP {$response->status()} en {$endpoint}.",
                httpStatus: $response->status(),
            );
        }

        $data = DataForSeoResponseData::from($response->json() ?? []);

        $this->record($method, $endpoint, $response->status(), $durationMs, $data->statusCode, $data->cost);

        if ($data->isSuccessful()) {
            return $data;
        }

        if ($data->isTransientError()) {
            throw new DataForSeoTransientException(
                $data->statusMessage,
                apiStatusCode: $data->statusCode,
                httpStatus: $response->status(),
            );
        }

        throw new DataForSeoPermanentException(
            $data->statusMessage,
            apiStatusCode: $data->statusCode,
            httpStatus: $response->status(),
        );
    }

    private function record(
        string $method,
        string $endpoint,
        int $httpStatus,
        int $durationMs,
        ?int $apiStatusCode,
        ?float $cost,
    ): void {
        Log::info('dataforseo.request', [
            'method' => $method,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'duration_ms' => $durationMs,
            'api_status_code' => $apiStatusCode,
            'cost' => $cost,
        ]);

        DataForSeoRequestCompleted::dispatch($method, $endpoint, $httpStatus, $durationMs, $apiStatusCode, $cost);
    }

    private function throttle(): void
    {
        if (! RateLimiter::tooManyAttempts(self::RATE_LIMIT_KEY, $this->rateLimitPerMinute)) {
            RateLimiter::hit(self::RATE_LIMIT_KEY, 60);

            return;
        }

        $wait = RateLimiter::availableIn(self::RATE_LIMIT_KEY);

        Log::warning('dataforseo.rate_limited', ['wait_seconds' => $wait]);

        Sleep::sleep(max($wait, 1));

        RateLimiter::hit(self::RATE_LIMIT_KEY, 60);
    }
}
