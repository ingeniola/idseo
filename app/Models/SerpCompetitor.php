<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SerpCompetitorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $domain
 * @property int $keywords_overlap
 * @property string $avg_position
 * @property string $visibility_score
 * @property string $calculated_at
 */
class SerpCompetitor extends Model
{
    /** @use HasFactory<SerpCompetitorFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'domain',
        'keywords_overlap',
        'avg_position',
        'visibility_score',
        'calculated_at',
    ];

    /**
     * calculated_at NO se castea a 'date' (ver ProjectVisibilitySnapshot/
     * CostLedger/Ranking): se maneja como string 'Y-m-d' para no romper
     * whereBetween.
     */
    protected function casts(): array
    {
        return [
            'avg_position' => 'decimal:2',
            'visibility_score' => 'decimal:2',
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
