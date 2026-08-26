<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\Keyword;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Evolución de la posición de una keyword en el tiempo (Fase 1, paso 9
 * del SPEC: "gráfica de evolución por keyword"). El eje Y se invierte
 * porque en rank tracking una posición MENOR es mejor (posición 1 =
 * primer lugar), así que "arriba" en la gráfica siempre significa
 * mejor ranking, no un valor más alto.
 *
 * No se registra en ningún panel/dashboard: se monta directamente con
 * la directiva Livewire (arroba)livewire() dentro del modal de la
 * acción "Ver evolución" de KeywordsRelationManager, pasándole el
 * Keyword como $record.
 */
class KeywordRankingChartWidget extends ChartWidget
{
    public ?Keyword $record = null;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->record !== null
            ? __('keywords.ranking_history.chart_heading', ['keyword' => $this->record->keyword])
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if ($this->record === null) {
            return ['datasets' => [], 'labels' => []];
        }

        $rankings = $this->record->rankings()
            ->orderBy('checked_at')
            ->get(['checked_at', 'position']);

        return [
            'datasets' => [
                [
                    'label' => __('keywords.ranking_history.dataset_label'),
                    'data' => $rankings->pluck('position')->all(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => '#6366f1',
                    'spanGaps' => true,
                ],
            ],
            'labels' => $rankings->pluck('checked_at')->all(),
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
                    'reverse' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
