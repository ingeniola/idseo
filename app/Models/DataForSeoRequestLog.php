<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DataForSeoRequestLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $method
 * @property string $endpoint
 * @property int $duration_ms
 * @property int|null $http_status
 * @property int|null $api_status_code
 * @property string|null $cost
 */
class DataForSeoRequestLog extends Model
{
    /** @use HasFactory<DataForSeoRequestLogFactory> */
    use HasFactory;

    protected $table = 'dataforseo_requests';

    public $timestamps = false;

    protected $fillable = [
        'method',
        'endpoint',
        'duration_ms',
        'http_status',
        'api_status_code',
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
}
