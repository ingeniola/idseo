<?php

declare(strict_types=1);

namespace App\Reports;

use Illuminate\Support\Collection;

/**
 * Genera las coordenadas de un gráfico de línea simple como SVG
 * inline, sin JavaScript ni librerías externas (ni Chart.js, ni una
 * CDN). Browsershot renderiza el PDF con Chromium headless: depender
 * de un script que dibuje en un <canvas> obligaría a esperar a que
 * corra antes de tomar el snapshot, y de una CDN externa a que el
 * servidor que la genere tenga salida a internet en ese momento.
 * Un <svg> con coordenadas ya calculadas en PHP siempre se ve igual,
 * sin esa dependencia.
 *
 * visibility_score siempre es 0-100 (ver CalculateProjectVisibility),
 * así que el eje Y está fijo a ese rango en vez de auto-escalar al
 * min/max de los datos.
 */
final class SvgLineChart
{
    private const MIN_Y = 0.0;

    private const MAX_Y = 100.0;

    public function __construct(
        private readonly int $width = 640,
        private readonly int $height = 220,
        private readonly int $padding = 32,
    ) {}

    /**
     * @param  Collection<int, array{date: string, visibility_score: float}>  $points
     * @return array{width: int, height: int, polyline: string, dots: array<int, array{x: float, y: float}>, labels: array<int, array{x: float, label: string}>}
     */
    public function build(Collection $points): array
    {
        if ($points->isEmpty()) {
            return ['width' => $this->width, 'height' => $this->height, 'polyline' => '', 'dots' => [], 'labels' => []];
        }

        $innerWidth = $this->width - ($this->padding * 2);
        $innerHeight = $this->height - ($this->padding * 2);
        $count = $points->count();

        $coords = $points->values()->map(function (array $point, int $index) use ($count, $innerWidth, $innerHeight) {
            $x = $this->padding + ($count > 1 ? ($index / ($count - 1)) * $innerWidth : $innerWidth / 2);
            $ratio = ($point['visibility_score'] - self::MIN_Y) / (self::MAX_Y - self::MIN_Y);
            $ratio = max(0.0, min(1.0, $ratio));
            $y = $this->padding + $innerHeight - ($ratio * $innerHeight);

            return ['x' => round($x, 2), 'y' => round($y, 2), 'label' => $point['date']];
        });

        return [
            'width' => $this->width,
            'height' => $this->height,
            'polyline' => $coords->map(fn (array $c) => "{$c['x']},{$c['y']}")->implode(' '),
            'dots' => $coords->map(fn (array $c) => ['x' => $c['x'], 'y' => $c['y']])->all(),
            'labels' => $coords->map(fn (array $c) => ['x' => $c['x'], 'label' => $c['label']])->all(),
        ];
    }
}
