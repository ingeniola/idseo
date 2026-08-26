<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertType;
use Database\Factories\RankingAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $keyword_id
 * @property AlertType $type
 * @property int|null $previous_position
 * @property int|null $current_position
 * @property string $triggered_at
 * @property Carbon|null $notified_at
 * @property Carbon|null $created_at
 */
class RankingAlert extends Model
{
    /** @use HasFactory<RankingAlertFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'project_id',
        'keyword_id',
        'type',
        'previous_position',
        'current_position',
        'triggered_at',
        'notified_at',
        'created_at',
    ];

    /**
     * triggered_at NO se castea a 'date' (ver Ranking/CostLedger): se
     * maneja como string 'Y-m-d'.
     */
    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'notified_at' => 'datetime',
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
     * @return BelongsTo<Keyword, $this>
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
