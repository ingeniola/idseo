<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Separación estricta de autorización (sección 9 del SPEC), la
 * contraparte de EnsureUserIsClient: las rutas de conexión de Search
 * Console (Fase 2) son rutas planas de Laravel, no recursos de
 * Filament, así que no pasan por el propio gate de
 * User::canAccessPanel() del panel — necesitan su propio guardia
 * explícito para que un usuario `client` no pueda alcanzarlas.
 */
class EnsureUserIsInternal
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user?->role->isInternal()) {
            $this->auditLogger->log(
                AuditEvent::AuthorizationDenied,
                user: $user,
                context: ['reason' => 'not_internal_role', 'path' => $request->path()],
            );

            abort(403);
        }

        return $next($request);
    }
}
