<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use Illuminate\Auth\Events\Failed;

/**
 * $event->credentials trae la contraseña en crudo (sección 9 del
 * SPEC: "logs sin credenciales") — solo se guarda el correo
 * intentado, nunca el arreglo completo.
 */
class RecordFailedLogin
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Failed $event): void
    {
        $this->auditLogger->log(
            AuditEvent::LoginFailed,
            email: $event->credentials['email'] ?? null,
            context: ['guard' => $event->guard],
        );
    }
}
