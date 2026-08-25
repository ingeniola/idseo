<?php

declare(strict_types=1);

namespace App\Models;

use App\DataForSeo\Enums\DataForSeoTaskStatus;
use Database\Factories\DataForSeoTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $task_id
 * @property string $endpoint
 * @property string|null $taskable_type
 * @property int|null $taskable_id
 * @property DataForSeoTaskStatus $status
 * @property array<string, mixed> $payload_sent
 * @property string|null $payload_received
 * @property string|null $cost
 * @property Carbon|null $posted_at
 * @property Carbon|null $completed_at
 * @property string|null $error_message
 * @property int $retry_count
 */
class DataForSeoTask extends Model
{
    /** @use HasFactory<DataForSeoTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id',
        'endpoint',
        'taskable_type',
        'taskable_id',
        'status',
        'payload_sent',
        'payload_received',
        'cost',
        'posted_at',
        'completed_at',
        'error_message',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => DataForSeoTaskStatus::class,
            'payload_sent' => 'array',
            'cost' => 'decimal:6',
            'posted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }
}
