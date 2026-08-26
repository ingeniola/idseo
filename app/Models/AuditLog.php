<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuditEvent;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property AuditEvent $event
 * @property int|null $user_id
 * @property string|null $email
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon|null $created_at
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'event',
        'user_id',
        'email',
        'ip_address',
        'user_agent',
        'context',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event' => AuditEvent::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
