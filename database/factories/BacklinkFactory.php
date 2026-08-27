<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Backlink;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backlink>
 */
class BacklinkFactory extends Factory
{
    protected $model = Backlink::class;

    public function definition(): array
    {
        $sourceDomain = $this->faker->domainName();
        $sourceUrl = 'https://'.$sourceDomain.'/'.$this->faker->slug();
        $targetUrl = 'https://'.$this->faker->domainName().'/'.$this->faker->slug();

        return [
            'project_id' => Project::factory(),
            'source_url' => $sourceUrl,
            'source_domain' => $sourceDomain,
            'target_url' => $targetUrl,
            'anchor' => $this->faker->words(3, true),
            'dofollow' => $this->faker->boolean(80),
            'first_seen' => now()->subMonths($this->faker->numberBetween(0, 12))->toDateString(),
            'last_seen' => now()->toDateString(),
            'is_lost' => false,
            'domain_rank' => $this->faker->numberBetween(0, 1000),
            'link_hash' => Backlink::hashFor($sourceUrl, $targetUrl),
        ];
    }
}
