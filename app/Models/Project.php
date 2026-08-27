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
 * @property string|null $google_business_place_id
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
        'google_business_place_id',
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

    /**
     * Fase 2: integración con Google Search Console (sección 5 del
     * SPEC). Una sola conexión por proyecto (unique en project_id).
     *
     * @return HasOne<SearchConsoleConnection, $this>
     */
    public function searchConsoleConnection(): HasOne
    {
        return $this->hasOne(SearchConsoleConnection::class);
    }

    /**
     * @return HasMany<SearchConsoleMetric, $this>
     */
    public function searchConsoleMetrics(): HasMany
    {
        return $this->hasMany(SearchConsoleMetric::class);
    }

    /**
     * Fase 3: backlinks (sección 5 del SPEC). Incluye tanto el perfil
     * propio del proyecto (domain = projects.domain) como
     * instantáneas de dominios competidores guardadas para comparar —
     * ver el docblock de la migración.
     *
     * @return HasMany<BacklinkSummary, $this>
     */
    public function backlinkSummaries(): HasMany
    {
        return $this->hasMany(BacklinkSummary::class);
    }

    /**
     * Último perfil de backlinks del propio dominio del proyecto (no
     * de un competidor comparado).
     *
     * @return HasOne<BacklinkSummary, $this>
     */
    public function latestOwnBacklinkSummary(): HasOne
    {
        return $this->hasOne(BacklinkSummary::class)
            ->where('backlink_summaries.domain', $this->domain)
            ->latestOfMany('captured_at');
    }

    /**
     * @return HasMany<Backlink, $this>
     */
    public function backlinks(): HasMany
    {
        return $this->hasMany(Backlink::class);
    }

    /**
     * @return HasMany<ReferringDomain, $this>
     */
    public function referringDomains(): HasMany
    {
        return $this->hasMany(ReferringDomain::class);
    }

    /**
     * Fase 3: auditoría técnica on-page (sección 5 del SPEC).
     *
     * @return HasMany<SiteAudit, $this>
     */
    public function siteAudits(): HasMany
    {
        return $this->hasMany(SiteAudit::class);
    }

    /**
     * Fase 3: monitoreo de reseñas y Google Business Profile (sección
     * 5 del SPEC).
     *
     * @return HasMany<BusinessReview, $this>
     */
    public function businessReviews(): HasMany
    {
        return $this->hasMany(BusinessReview::class);
    }

    /**
     * Fase 3: monitoreo de menciones en LLMs / GEO (sección 5 del
     * SPEC).
     *
     * @return HasMany<LlmMention, $this>
     */
    public function llmMentions(): HasMany
    {
        return $this->hasMany(LlmMention::class);
    }
}
