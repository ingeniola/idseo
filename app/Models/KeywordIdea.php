<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KeywordIdeaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $session_id
 * @property string $keyword
 * @property int|null $search_volume
 * @property string|null $cpc
 * @property string|null $competition
 * @property int|null $difficulty
 * @property string|null $intent
 * @property bool $is_selected
 */
class KeywordIdea extends Model
{
    /** @use HasFactory<KeywordIdeaFactory> */
    use HasFactory;

    protected $fillable = [
        'session_id',
        'keyword',
        'search_volume',
        'cpc',
        'competition',
        'difficulty',
        'intent',
        'is_selected',
    ];

    protected function casts(): array
    {
        return [
            'cpc' => 'decimal:2',
            'competition' => 'decimal:2',
            'is_selected' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<KeywordResearchSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(KeywordResearchSession::class, 'session_id');
    }
}
