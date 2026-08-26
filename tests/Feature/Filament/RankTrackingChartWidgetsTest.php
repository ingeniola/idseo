<?php

declare(strict_types=1);

use App\Filament\Widgets\KeywordRankingChartWidget;
use App\Filament\Widgets\ProjectVisibilityChartWidget;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use App\Models\Ranking;
use Filament\Widgets\ChartWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * getData() es protected en ChartWidget: se invoca por reflexión en
 * vez de montar el componente completo vía Livewire, que exigiría
 * simular la sesión de Filament sin aportar nada a lo que se está
 * probando (la forma de los datos que arma cada widget).
 */
function chartWidgetData(ChartWidget $widget): array
{
    $method = new ReflectionMethod($widget, 'getData');
    $method->setAccessible(true);

    return $method->invoke($widget);
}

test('KeywordRankingChartWidget arma labels y posiciones en orden cronologico', function () {
    $keyword = Keyword::factory()->create();
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-08', 'position' => 5]);
    Ranking::factory()->create(['keyword_id' => $keyword->id, 'checked_at' => '2026-08-01', 'position' => 10]);

    $widget = new KeywordRankingChartWidget;
    $widget->record = $keyword;

    $data = chartWidgetData($widget);

    expect($data['labels'])->toBe(['2026-08-01', '2026-08-08'])
        ->and($data['datasets'][0]['data'])->toBe([10, 5]);
});

test('KeywordRankingChartWidget sin record devuelve datos vacios', function () {
    $widget = new KeywordRankingChartWidget;

    $data = chartWidgetData($widget);

    expect($data)->toBe(['datasets' => [], 'labels' => []]);
});

test('KeywordRankingChartWidget expone el nombre de la keyword en el heading', function () {
    $keyword = Keyword::factory()->create(['keyword' => 'zapatos deportivos']);

    $widget = new KeywordRankingChartWidget;
    $widget->record = $keyword;

    expect($widget->getHeading())->toContain('zapatos deportivos');
});

test('ProjectVisibilityChartWidget arma labels y scores en orden cronologico', function () {
    $project = Project::factory()->create();
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-08-08', 'visibility_score' => 42.5]);
    ProjectVisibilitySnapshot::factory()->create(['project_id' => $project->id, 'calculated_at' => '2026-08-01', 'visibility_score' => 30.0]);

    $widget = new ProjectVisibilityChartWidget;
    $widget->record = $project;

    $data = chartWidgetData($widget);

    expect($data['labels'])->toBe(['2026-08-01', '2026-08-08'])
        ->and($data['datasets'][0]['data'])->toBe([30.0, 42.5]);
});

test('ProjectVisibilityChartWidget sin record devuelve datos vacios', function () {
    $widget = new ProjectVisibilityChartWidget;

    $data = chartWidgetData($widget);

    expect($data)->toBe(['datasets' => [], 'labels' => []]);
});
