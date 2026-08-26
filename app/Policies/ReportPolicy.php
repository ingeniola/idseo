<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;

/**
 * Igual que ProjectPolicy: before() mantiene sin restricciones al
 * equipo interno (incluyendo el tab "Reports" de ReportsRelationManager
 * y su DeleteBulkAction, que Filament autoriza automáticamente en
 * cuanto existe esta clase). view() es lo que usa
 * DownloadReportController para que un usuario del portal solo pueda
 * descargar reportes de proyectos de su propio cliente.
 *
 * __call() existe por la misma razón que en ProjectPolicy: el Gate de
 * Laravel solo invoca before() si is_callable() considera "definida"
 * la habilidad pedida (ver Gate::resolvePolicyCallback()) — sin esto,
 * una habilidad como deleteAny (que Filament pide automáticamente
 * para el DeleteBulkAction de ReportsRelationManager) se negaría sin
 * ni siquiera pasar por before(), rompiendo esa acción para el equipo
 * interno.
 */
class ReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role->isInternal() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Client;
    }

    public function view(User $user, Report $report): bool
    {
        return $user->role === UserRole::Client && $user->client_id === $report->project->client_id;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $ability, array $arguments): bool
    {
        return false;
    }
}
