<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $language_code
 * @property string $language_name
 * @property bool $valid_for_google_ads_keywords
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $primaryKey = 'language_code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'language_code',
        'language_name',
        'valid_for_google_ads_keywords',
    ];

    protected function casts(): array
    {
        return [
            'valid_for_google_ads_keywords' => 'boolean',
        ];
    }
}
