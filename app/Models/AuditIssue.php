<?php

declare(strict_types=1);

namespace App\Models;

use App\DataForSeo\OnPage\Enums\AuditIssueSeverity;
use App\DataForSeo\OnPage\Enums\AuditIssueType;
use Database\Factories\AuditIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $audit_id
 * @property string $url
 * @property AuditIssueType $issue_type
 * @property AuditIssueSeverity $severity
 * @property string $message
 * @property array<string, mixed>|null $details
 */
class AuditIssue extends Model
{
    /** @use HasFactory<AuditIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'audit_id',
        'url',
        'issue_type',
        'severity',
        'message',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'issue_type' => AuditIssueType::class,
            'severity' => AuditIssueSeverity::class,
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<SiteAudit, $this>
     */
    public function audit(): BelongsTo
    {
        return $this->belongsTo(SiteAudit::class, 'audit_id');
    }
}
