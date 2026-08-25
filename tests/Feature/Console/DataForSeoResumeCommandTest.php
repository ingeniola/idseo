<?php

declare(strict_types=1);

use App\DataForSeo\CostControl\CircuitBreaker;

test('avisa que no hay nada que reanudar si el circuit breaker no esta activo', function () {
    $this->artisan('dataforseo:resume')
        ->expectsOutputToContain('no está activo')
        ->assertExitCode(0);
});

test('reanuda el circuit breaker cuando el usuario confirma', function () {
    app(CircuitBreaker::class)->trip('prueba');

    $this->artisan('dataforseo:resume')
        ->expectsConfirmation('¿Confirmas que revisaste el gasto y quieres reanudar la creación de tareas?', 'yes')
        ->assertExitCode(0);

    expect(app(CircuitBreaker::class)->isTripped())->toBeFalse();
});

test('no reanuda el circuit breaker si el usuario cancela', function () {
    app(CircuitBreaker::class)->trip('prueba');

    $this->artisan('dataforseo:resume')
        ->expectsConfirmation('¿Confirmas que revisaste el gasto y quieres reanudar la creación de tareas?', 'no')
        ->assertExitCode(0);

    expect(app(CircuitBreaker::class)->isTripped())->toBeTrue();
});
