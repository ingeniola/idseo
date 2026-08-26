<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

/**
 * Autoriza el acceso de solo lectura del portal de cliente (sección
 * 5.5 del SPEC) a sus propios proyectos. El equipo interno sigue
 * teniendo acceso total sin restricciones vía before().
 *
 * Existir esta clase activa automáticamente la autorización por
 * política en ProjectResource/KeywordsRelationManager/
 * ReportsRelationManager de Filament (Filament\Resources\Resource\
 * Concerns\HasAuthorization: si existe un método con el nombre de la
 * habilidad en la política del modelo, Filament lo usa).
 *
 * El Gate de Laravel solo llama a before() si el método específico de
 * la habilidad "existe" según is_callable() — y niega derecho sin
 * siquiera preguntar antes si no existe (ver
 * Gate::resolvePolicyCallback()). __call() hace que is_callable()
 * considere "definida" cualquier habilidad (create, update, delete,
 * deleteAny, restore...) para que before() sí llegue a evaluarse y
 * el equipo interno conserve acceso total a habilidades que esta
 * clase no define explícitamente. Si before() no aplica (usuario no
 * interno), __call() niega por defecto: el portal es de solo lectura.
 */
class ProjectPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role->isInternal() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Client;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->role === UserRole::Client && $user->client_id === $project->client_id;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $ability, array $arguments): bool
    {
        return false;
    }
}
