<?php

declare(strict_types=1);

namespace Database\Factories;

use App\DataForSeo\OnPage\Enums\AuditIssueType;
use App\Models\AuditIssue;
use App\Models\SiteAudit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditIssue>
 */
class AuditIssueFactory extends Factory
{
    protected $model = AuditIssue::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(AuditIssueType::cases());

        return [
            'audit_id' => SiteAudit::factory(),
            'url' => $this->faker->url(),
            'issue_type' => $type,
            'severity' => $type->severity(),
            'message' => $type->message(),
            'details' => null,
        ];
    }
}
