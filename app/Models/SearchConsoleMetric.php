<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SearchConsoleMetricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $date
 * @property string $query
 * @property int $clicks
 * @property int $impressions
 * @property string $ctr
 * @property string $position
 */
class SearchConsoleMetric extends Model
{
    /** @use HasFactory<SearchConsoleMetricFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'date',
        'query',
        'clicks',
        'impressions',
        'ctr',
        'position',
    ];

    /**
     * date NO se castea a 'date' (ver Ranking/CostLedger): se maneja
     * como string 'Y-m-d'.
     */
    protected function casts(): array
    {
        return [
            'ctr' => 'decimal:4',
            'position' => 'decimal:2',
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
