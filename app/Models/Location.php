<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $location_code
 * @property string $location_name
 * @property string $location_name_canonical
 * @property string $country_iso_code
 * @property string $location_type
 * @property int|null $parent_code
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected $primaryKey = 'location_code';

    protected $keyType = 'int';

    public $incrementing = false;

    protected $fillable = [
        'location_code',
        'location_name',
        'location_name_canonical',
        'country_iso_code',
        'location_type',
        'parent_code',
    ];

    /**
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_code', 'location_code');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_code', 'location_code');
    }
}
