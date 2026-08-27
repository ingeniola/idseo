<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BacklinkSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $domain
 * @property string $captured_at
 * @property int $total_backlinks
 * @property int $referring_domains
 * @property int $referring_ips
 * @property int|null $domain_rank
 * @property int $broken_backlinks
 * @property array<string, mixed>|null $raw
 */
class BacklinkSummary extends Model
{
    /** @use HasFactory<BacklinkSummaryFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'domain',
        'captured_at',
        'total_backlinks',
        'referring_domains',
        'referring_ips',
        'domain_rank',
        'broken_backlinks',
        'raw',
    ];

    /**
     * captured_at NO se castea a 'date' (ver Ranking/CostLedger): se
     * maneja como string 'Y-m-d'.
     */
    protected function casts(): array
    {
        return [
            'raw' => 'array',
        ];
    }

    public function isOwnDomain(): bool
    {
        return $this->domain === $this->project->domain;
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
