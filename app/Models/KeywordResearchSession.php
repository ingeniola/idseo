<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KeywordResearchSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int|null $user_id
 * @property string $seed_keyword
 * @property string $source_endpoint
 * @property string $cost
 * @property Carbon|null $created_at
 */
class KeywordResearchSession extends Model
{
    /** @use HasFactory<KeywordResearchSessionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'user_id',
        'seed_keyword',
        'source_endpoint',
        'cost',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:6',
            'created_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<KeywordIdea, $this>
     */
    public function keywordIdeas(): HasMany
    {
        return $this->hasMany(KeywordIdea::class, 'session_id');
    }
}
