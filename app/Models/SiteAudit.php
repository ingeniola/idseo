<?php

declare(strict_types=1);

namespace App\Models;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use Database\Factories\SiteAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $task_id
 * @property DataForSeoTaskStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $pages_crawled
 * @property string|null $onpage_score
 * @property string|null $cost
 */
class SiteAudit extends Model
{
    /** @use HasFactory<SiteAuditFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'task_id',
        'status',
        'started_at',
        'completed_at',
        'pages_crawled',
        'onpage_score',
        'cost',
    ];

    protected function casts(): array
    {
        return [
            'status' => DataForSeoTaskStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'onpage_score' => 'decimal:2',
            'cost' => 'decimal:6',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<AuditIssue, $this>
     */
    public function issues(): HasMany
    {
        return $this->hasMany(AuditIssue::class, 'audit_id');
    }
}
