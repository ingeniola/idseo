<?php

declare(strict_types=1);

namespace App\Models;

use App\DataForSeo\Enums\EndpointGroup;
use Database\Factories\CostLedgerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $date
 * @property int|null $client_id
 * @property int|null $project_id
 * @property EndpointGroup $endpoint_group
 * @property string $cost
 * @property string|null $task_reference
 */
class CostLedger extends Model
{
    /** @use HasFactory<CostLedgerFactory> */
    use HasFactory;

    protected $table = 'cost_ledger';

    protected $fillable = [
        'date',
        'client_id',
        'project_id',
        'endpoint_group',
        'cost',
        'task_reference',
    ];

    protected function casts(): array
    {
        return [
            'endpoint_group' => EndpointGroup::class,
            'cost' => 'decimal:6',
        ];
    }
}
