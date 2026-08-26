<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectVisibilitySnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $calculated_at
 * @property string $visibility_score
 * @property int $tracked_keywords_count
 * @property int $keywords_in_top_3
 * @property int $keywords_in_top_10
 * @property int $keywords_in_top_20
 * @property string|null $average_position
 */
class ProjectVisibilitySnapshot extends Model
{
    /** @use HasFactory<ProjectVisibilitySnapshotFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'calculated_at',
        'visibility_score',
        'tracked_keywords_count',
        'keywords_in_top_3',
        'keywords_in_top_10',
        'keywords_in_top_20',
        'average_position',
    ];

    /**
     * calculated_at NO se castea a 'date' (ver CostLedger/Ranking): se
     * maneja como string 'Y-m-d' para no romper whereBetween.
     */
    protected function casts(): array
    {
        return [
            'visibility_score' => 'decimal:2',
            'average_position' => 'decimal:2',
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
