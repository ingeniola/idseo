<?php

declare(strict_types=1);

namespace App\Audit;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function log(AuditEvent $event, ?User $user = null, ?string $email = null, array $context = []): void
    {
        AuditLog::query()->create([
            'event' => $event,
            'user_id' => $user?->id,
            'email' => $email ?? $user?->email,
            'ip_address' => $this->request->ip(),
            'user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }
}
