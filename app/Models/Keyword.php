<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KeywordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $keyword
 * @property int $location_code
 * @property string $language_code
 * @property array<int, string>|null $tags
 * @property int|null $search_volume
 * @property string|null $cpc
 * @property string|null $competition
 * @property Carbon|null $volume_updated_at
 * @property bool $is_active
 */
class Keyword extends Model
{
    /** @use HasFactory<KeywordFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'keyword',
        'location_code',
        'language_code',
        'tags',
        'search_volume',
        'cpc',
        'competition',
        'volume_updated_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'cpc' => 'decimal:2',
            'competition' => 'decimal:2',
            'volume_updated_at' => 'datetime',
            'is_active' => 'boolean',
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
     * @return HasMany<Ranking, $this>
     */
    public function rankings(): HasMany
    {
        return $this->hasMany(Ranking::class);
    }

    /**
     * @return HasMany<SerpSnapshot, $this>
     */
    public function serpSnapshots(): HasMany
    {
        return $this->hasMany(SerpSnapshot::class);
    }

    /**
     * @return HasOne<Ranking, $this>
     */
    public function latestRanking(): HasOne
    {
        return $this->hasOne(Ranking::class)->latestOfMany('checked_at');
    }

    /**
     * @return HasOne<SerpSnapshot, $this>
     */
    public function latestSerpSnapshot(): HasOne
    {
        return $this->hasOne(SerpSnapshot::class)->latestOfMany('captured_at');
    }

    /**
     * Fase 2: alertas (sección 5 del SPEC), calculadas por
     * DetectRankingAlerts.
     *
     * @return HasMany<RankingAlert, $this>
     */
    public function rankingAlerts(): HasMany
    {
        return $this->hasMany(RankingAlert::class);
    }
}
