<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $language_code
 * @property string $language_name
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
    ];
}
