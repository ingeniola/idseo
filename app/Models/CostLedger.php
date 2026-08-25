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

    /**
     * `date` NO se castea a 'date': Eloquent guarda ese cast con sufijo
     * " 00:00:00" (usa el dateFormat de la conexión), lo que rompe
     * comparaciones whereBetween con strings 'Y-m-d' planos. Se maneja
     * como string 'Y-m-d' consistentemente.
     */
    protected function casts(): array
    {
        return [
            'endpoint_group' => EndpointGroup::class,
            'cost' => 'decimal:6',
        ];
    }
}
