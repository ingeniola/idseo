<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Separación estricta de autorización (sección 9 del SPEC, el mismo
 * principio detrás de User::canAccessPanel() para el panel interno):
 * el portal de cliente es exclusivamente para usuarios con rol
 * `client`. Un usuario interno autenticado que intente entrar al
 * portal se rechaza igual que un visitante sin sesión.
 */
class EnsureUserIsClient
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user?->role !== UserRole::Client) {
            $this->auditLogger->log(
                AuditEvent::AuthorizationDenied,
                user: $user,
                context: ['reason' => 'not_client_role', 'path' => $request->path()],
            );

            abort(403);
        }

        return $next($request);
    }
}
