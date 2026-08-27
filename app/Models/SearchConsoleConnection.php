<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SearchConsoleConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $site_url
 * @property string $access_token
 * @property string $refresh_token
 * @property Carbon $expires_at
 * @property int|null $connected_by
 */
class SearchConsoleConnection extends Model
{
    /** @use HasFactory<SearchConsoleConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'site_url',
        'access_token',
        'refresh_token',
        'expires_at',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Margen de 60s para no arrancar una llamada con un token que
     * expira a mitad de la petición.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->subSeconds(60)->isPast();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }
}
