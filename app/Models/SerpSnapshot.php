<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SerpSnapshotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $keyword_id
 * @property Carbon $captured_at
 * @property array<string, mixed> $raw_response
 * @property array<int, array<string, mixed>> $top_results
 */
class SerpSnapshot extends Model
{
    /** @use HasFactory<SerpSnapshotFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'keyword_id',
        'captured_at',
        'raw_response',
        'top_results',
    ];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'raw_response' => 'array',
            'top_results' => 'array',
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
