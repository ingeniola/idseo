<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReportTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $client_id
 * @property string $name
 * @property array<string, mixed> $sections
 * @property array<string, mixed>|null $branding_overrides
 */
class ReportTemplate extends Model
{
    /** @use HasFactory<ReportTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'client_id',
        'name',
        'sections',
        'branding_overrides',
    ];

    protected function casts(): array
    {
        return [
            'sections' => 'array',
            'branding_overrides' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<Report, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'template_id');
    }
}
