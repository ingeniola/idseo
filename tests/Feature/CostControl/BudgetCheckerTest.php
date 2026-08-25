<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\BudgetChecker;
use App\Models\CostLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('monthSpend suma solo el costo del mes de referencia', function () {
    $reference = Carbon::create(2026, 3, 15);

    CostLedger::factory()->create(['date' => '2026-03-01', 'cost' => 10]);
    CostLedger::factory()->create(['date' => '2026-03-31', 'cost' => 5]);
    CostLedger::factory()->create(['date' => '2026-02-28', 'cost' => 100]);
    CostLedger::factory()->create(['date' => '2026-04-01', 'cost' => 100]);

    expect((new BudgetChecker(0))->monthSpend($reference))->toEqual(15.0);
});

test('projectedMonthEnd proyecta linealmente segun los dias transcurridos', function () {
    $reference = Carbon::create(2026, 3, 10);

    CostLedger::factory()->create(['date' => '2026-03-05', 'cost' => 20]);

    $checker = new BudgetChecker(0);

    // 20 gastados en 10 días transcurridos, marzo tiene 31 días.
    expect($checker->projectedMonthEnd($reference))->toEqual(20 / 10 * 31);
});

test('isOverGlobalBudget es falso cuando no hay presupuesto configurado', function () {
    CostLedger::factory()->create(['date' => Carbon::now()->toDateString(), 'cost' => 999_999]);

    expect((new BudgetChecker(0))->isOverGlobalBudget())->toBeFalse();
});

test('isOverGlobalBudget es verdadero cuando el gasto del mes alcanza el presupuesto', function () {
    $reference = Carbon::now();

    CostLedger::factory()->create(['date' => $reference->toDateString(), 'cost' => 50]);

    $checker = new BudgetChecker(50);

    expect($checker->isOverGlobalBudget($reference))->toBeTrue();
});
