<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BacklinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $project_id
 * @property string $source_url
 * @property string $source_domain
 * @property string $target_url
 * @property string|null $anchor
 * @property bool $dofollow
 * @property string $first_seen
 * @property string $last_seen
 * @property bool $is_lost
 * @property int|null $domain_rank
 * @property string $link_hash
 */
class Backlink extends Model
{
    /** @use HasFactory<BacklinkFactory> */
    use HasFactory;

    protected $fillable = [
        'project_id',
        'source_url',
        'source_domain',
        'target_url',
        'anchor',
        'dofollow',
        'first_seen',
        'last_seen',
        'is_lost',
        'domain_rank',
        'link_hash',
    ];

    /**
     * first_seen/last_seen NO se castean a 'date' (ver Ranking/CostLedger).
     */
    protected function casts(): array
    {
        return [
            'dofollow' => 'boolean',
            'is_lost' => 'boolean',
        ];
    }

    public static function hashFor(string $sourceUrl, string $targetUrl): string
    {
        return sha1($sourceUrl.'|'.$targetUrl);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
