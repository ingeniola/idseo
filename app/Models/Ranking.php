<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RankingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $keyword_id
 * @property string $checked_at
 * @property int|null $position
 * @property int|null $previous_position
 * @property string|null $url
 * @property array<string, mixed>|null $serp_features
 * @property int|null $estimated_traffic
 * @property bool $is_featured_snippet
 * @property bool $is_local_pack
 */
class Ranking extends Model
{
    /** @use HasFactory<RankingFactory> */
    use HasFactory;

    protected $fillable = [
        'keyword_id',
        'checked_at',
        'position',
        'previous_position',
        'url',
        'serp_features',
        'estimated_traffic',
        'is_featured_snippet',
        'is_local_pack',
    ];

    /**
     * checked_at NO se castea a 'date': Eloquent guarda ese cast con
     * sufijo " 00:00:00" (usa el dateFormat de la conexión), lo que
     * rompe comparaciones whereBetween con strings 'Y-m-d' planos (ver
     * CostLedger). Se maneja como string 'Y-m-d' consistentemente.
     */
    protected function casts(): array
    {
        return [
            'serp_features' => 'array',
            'is_featured_snippet' => 'boolean',
            'is_local_pack' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Keyword, $this>
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }
}
