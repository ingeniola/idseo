<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
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
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->role === UserRole::Client, 403);

        return $next($request);
    }
}
