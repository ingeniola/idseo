<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SearchEngine;
use App\Enums\TargetType;
use App\Enums\TrackingFrequency;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string $domain
 * @property TargetType $target_type
 * @property int $default_location_code
 * @property string $default_language_code
 * @property SearchEngine $search_engine
 * @property TrackingFrequency $tracking_frequency
 * @property bool $is_active
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'domain',
        'target_type',
        'default_location_code',
        'default_language_code',
        'search_engine',
        'tracking_frequency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => TargetType::class,
            'search_engine' => SearchEngine::class,
            'tracking_frequency' => TrackingFrequency::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Keyword, $this>
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /**
     * Usuarios internos asignados al proyecto (sección 5.1 del SPEC).
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * @return HasMany<ProjectVisibilitySnapshot, $this>
     */
    public function visibilitySnapshots(): HasMany
    {
        return $this->hasMany(ProjectVisibilitySnapshot::class);
    }

    /**
     * @return HasOne<ProjectVisibilitySnapshot, $this>
     */
    public function latestVisibilitySnapshot(): HasOne
    {
        return $this->hasOne(ProjectVisibilitySnapshot::class)->latestOfMany('calculated_at');
    }

    /**
     * Fase 2: análisis de competidores derivado de los SERP snapshots
     * (sección 5 del SPEC), calculado por CalculateSerpCompetitors.
     *
     * @return HasMany<SerpCompetitor, $this>
     */
    public function serpCompetitors(): HasMany
    {
        return $this->hasMany(SerpCompetitor::class);
    }

    /**
     * Fase 2: investigación de keywords (sección 5 del SPEC).
     *
     * @return HasMany<KeywordResearchSession, $this>
     */
    public function keywordResearchSessions(): HasMany
    {
        return $this->hasMany(KeywordResearchSession::class);
    }

    /**
     * Ideas de todas las sesiones de investigación del proyecto, para
     * la pestaña de "Investigación de keywords" en Editar Proyecto.
     *
     * @return HasManyThrough<KeywordIdea, KeywordResearchSession, $this>
     */
    public function keywordIdeas(): HasManyThrough
    {
        return $this->hasManyThrough(
            KeywordIdea::class,
            KeywordResearchSession::class,
            'project_id',
            'session_id',
        );
    }
}
