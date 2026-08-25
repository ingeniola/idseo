<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\BudgetChecker;
use App\DataForSeo\CostControl\CircuitBreaker;
use App\DataForSeo\Events\CostBudgetExceeded;
use App\Models\CostLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('checkGlobalBudget no corta cuando no hay presupuesto configurado', function () {
    $circuitBreaker = new CircuitBreaker(new BudgetChecker(0));

    CostLedger::factory()->create(['date' => now()->toDateString(), 'cost' => 999_999]);

    expect($circuitBreaker->checkGlobalBudget())->toBeFalse()
        ->and($circuitBreaker->isTripped())->toBeFalse();
});

test('checkGlobalBudget corta y dispara CostBudgetExceeded al superar el presupuesto', function () {
    Event::fake();

    CostLedger::factory()->create(['date' => now()->toDateString(), 'cost' => 100]);

    $circuitBreaker = new CircuitBreaker(new BudgetChecker(50));

    expect($circuitBreaker->checkGlobalBudget())->toBeTrue()
        ->and($circuitBreaker->isTripped())->toBeTrue()
        ->and($circuitBreaker->reason())->toContain('Presupuesto global');

    Event::assertDispatched(CostBudgetExceeded::class);
});

test('checkGlobalBudget es idempotente: no vuelve a disparar el evento si ya estaba activo', function () {
    Event::fake();

    CostLedger::factory()->create(['date' => now()->toDateString(), 'cost' => 100]);

    $circuitBreaker = new CircuitBreaker(new BudgetChecker(50));

    $circuitBreaker->checkGlobalBudget();
    $circuitBreaker->checkGlobalBudget();

    Event::assertDispatchedTimes(CostBudgetExceeded::class, 1);
});

test('resume limpia el estado del circuit breaker', function () {
    $circuitBreaker = new CircuitBreaker(new BudgetChecker(0));

    $circuitBreaker->trip('corte manual de prueba');
    expect($circuitBreaker->isTripped())->toBeTrue();

    $circuitBreaker->resume();
    expect($circuitBreaker->isTripped())->toBeFalse();
});
