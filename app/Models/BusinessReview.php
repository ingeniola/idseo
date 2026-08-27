<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $review_id
 * @property string|null $reviewer_name
 * @property string|null $profile_image_url
 * @property int|null $rating
 * @property string|null $review_text
 * @property Carbon|null $published_at
 * @property string|null $owner_answer
 * @property Carbon|null $owner_answered_at
 * @property bool $is_local_guide
 * @property array<string, mixed>|null $raw
 */
class BusinessReview extends Model
{
    /** @use HasFactory<BusinessReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'review_id',
        'reviewer_name',
        'profile_image_url',
        'rating',
        'review_text',
        'published_at',
        'owner_answer',
        'owner_answered_at',
        'is_local_guide',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'owner_answered_at' => 'datetime',
            'is_local_guide' => 'boolean',
            'raw' => 'array',
        ];
    }

    public function hasOwnerAnswer(): bool
    {
        return filled($this->owner_answer);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
