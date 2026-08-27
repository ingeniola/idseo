<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReferringDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $domain
 * @property int $backlinks_count
 * @property string $first_seen
 * @property int|null $domain_rank
 * @property bool $is_lost
 */
class ReferringDomain extends Model
{
    /** @use HasFactory<ReferringDomainFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'domain',
        'backlinks_count',
        'first_seen',
        'domain_rank',
        'is_lost',
    ];

    /**
     * first_seen NO se castea a 'date' (ver Ranking/CostLedger).
     */
    protected function casts(): array
    {
        return [
            'is_lost' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
