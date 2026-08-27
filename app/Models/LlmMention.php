<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LlmMentionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $platform
 * @property string|null $question
 * @property string|null $answer
 * @property array<int, array<string, mixed>>|null $sources
 * @property Carbon $captured_at
 * @property array<string, mixed>|null $raw
 */
class LlmMention extends Model
{
    /** @use HasFactory<LlmMentionFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'platform',
        'question',
        'answer',
        'sources',
        'captured_at',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'sources' => 'array',
            'captured_at' => 'datetime',
            'raw' => 'array',
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
