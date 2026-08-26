<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\ProjectVisibilitySnapshot;
use App\Reports\SvgLineChart;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Vista de un solo proyecto en el portal (sección 5.5 del SPEC):
 * solo lectura, sin costos ni nada de DataForSEO. mount() autoriza
 * contra ProjectPolicy::view() — un usuario del portal solo puede
 * entrar aquí si el proyecto es de su propio cliente.
 */
#[Layout('layouts.portal')]
class ProjectShow extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);

        $this->project = $project;
    }

    public function render(): View
    {
        $keywords = $this->project->keywords()
            ->where('is_active', true)
            ->with('latestRanking')
            ->orderBy('keyword')
            ->get();

        $visibilityEvolution = $this->project->visibilitySnapshots()
            ->orderBy('calculated_at')
            ->get(['calculated_at', 'visibility_score'])
            ->map(fn (ProjectVisibilitySnapshot $snapshot) => [
                'date' => $snapshot->calculated_at,
                'visibility_score' => (float) $snapshot->visibility_score,
            ]);

        $chart = (new SvgLineChart)->build($visibilityEvolution);

        $reports = $this->project->reports()
            ->where('status', ReportStatus::Completed)
            ->orderByDesc('period_start')
            ->get();

        return view('livewire.portal.project-show', [
            'keywords' => $keywords,
            'hasVisibilityData' => $visibilityEvolution->isNotEmpty(),
            'chart' => $chart,
            'reports' => $reports,
        ]);
    }
}
