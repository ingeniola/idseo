<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Project;
use Filament\Widgets\ChartWidget;

/**
 * Visibilidad agregada del proyecto en el tiempo (Fase 1, paso 9 del
 * SPEC: "gráfica de visibilidad agregada del proyecto"), a partir de
 * los snapshots diarios que calcula CalculateProjectVisibility (Fase
 * 1, paso 8). visibility_score es un índice 0-100 de diseño propio —
 * ver el docblock de ese job — no un valor que devuelva DataForSEO.
 *
 * Se registra como footer widget de EditProject: Filament inyecta el
 * Project actual en la propiedad pública $record automáticamente
 * (InteractsWithRecord::getWidgetData()).
 */
class ProjectVisibilityChartWidget extends ChartWidget
{
    public ?Project $record = null;

    protected static bool $isLazy = false;

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): ?string
    {
        return __('projects.visibility_chart.heading');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if ($this->record === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $snapshots = $this->record->visibilitySnapshots()
            ->orderBy('calculated_at')
            ->get(['calculated_at', 'visibility_score']);

        return [
            'datasets' => [
                [
                    'label' => __('projects.visibility_chart.dataset_label'),
                    'data' => $snapshots->pluck('visibility_score')->map(fn ($value) => (float) $value)->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b981',
                ],
            ],
            'labels' => $snapshots->pluck('calculated_at')->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'min' => 0,
                    'max' => 100,
                ],
            ],
        ];
    }
}
