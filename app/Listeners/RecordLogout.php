<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Logout;

class RecordLogout
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Logout $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->auditLogger->log(AuditEvent::Logout, user: $event->user, context: ['guard' => $event->guard]);
    }
}
