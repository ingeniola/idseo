<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Audit\AuditLogger;
use App\Enums\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Events\Login;

class RecordSuccessfulLogin
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;

        $this->auditLogger->log(AuditEvent::LoginSucceeded, user: $user, context: ['guard' => $event->guard]);
    }
}
