<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Escritorio del portal: solo los proyectos del cliente del usuario
 * autenticado (sección 5.5 del SPEC — "acceso solo a sus proyectos").
 */
#[Layout('layouts.portal')]
class ProjectList extends Component
{
    public function render(): View
    {
        $projects = Auth::user()->client
            ->projects()
            ->with('latestVisibilitySnapshot')
            ->orderBy('name')
            ->get();

        return view('livewire.portal.project-list', ['projects' => $projects]);
    }
}
