<?php

declare(strict_types=1);

use App\Reports\SvgLineChart;
use Illuminate\Support\Collection;

test('con datos vacios no genera puntos', function () {
    $chart = new SvgLineChart;

    $result = $chart->build(new Collection);

    expect($result['polyline'])->toBe('')
        ->and($result['dots'])->toBe([])
        ->and($result['labels'])->toBe([]);
});

test('mapea visibility_score 0-100 a coordenadas Y dentro del área util', function () {
    $chart = new SvgLineChart(width: 100, height: 100, padding: 10);

    $points = new Collection([
        ['date' => '2026-08-01', 'visibility_score' => 0.0],
        ['date' => '2026-08-08', 'visibility_score' => 100.0],
    ]);

    $result = $chart->build($points);

    expect($result['dots'])->toHaveCount(2)
        ->and($result['dots'][0]['y'])->toBe(90.0)
        ->and($result['dots'][1]['y'])->toBe(10.0);
});

test('con un solo punto lo centra horizontalmente', function () {
    $chart = new SvgLineChart(width: 100, height: 100, padding: 10);

    $points = new Collection([
        ['date' => '2026-08-01', 'visibility_score' => 50.0],
    ]);

    $result = $chart->build($points);

    expect($result['dots'])->toHaveCount(1)
        ->and($result['dots'][0]['x'])->toBe(50.0);
});

test('los labels usan la fecha de cada punto', function () {
    $chart = new SvgLineChart;

    $points = new Collection([
        ['date' => '2026-08-01', 'visibility_score' => 10.0],
        ['date' => '2026-08-15', 'visibility_score' => 20.0],
    ]);

    $result = $chart->build($points);

    expect(array_column($result['labels'], 'label'))->toBe(['2026-08-01', '2026-08-15']);
});

test('valores fuera de 0-100 se recortan sin romper el layout', function () {
    $chart = new SvgLineChart(width: 100, height: 100, padding: 10);

    $points = new Collection([
        ['date' => '2026-08-01', 'visibility_score' => -20.0],
        ['date' => '2026-08-08', 'visibility_score' => 150.0],
    ]);

    $result = $chart->build($points);

    expect($result['dots'][0]['y'])->toBe(90.0)
        ->and($result['dots'][1]['y'])->toBe(10.0);
});
