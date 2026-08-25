<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed de desarrollo local. La sincronización real de `locations`/
     * `languages` se hace con `dataforseo:sync-locations` (Fase 1, paso
     * 5), no aquí.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin Ingenio',
            'email' => 'admin@ingenio.la',
            'role' => UserRole::Admin,
        ]);

        $client = Client::factory()->create([
            'name' => 'Cliente de prueba',
        ]);

        $project = Project::factory()->create([
            'client_id' => $client->id,
            'name' => 'Sitio principal',
        ]);

        Keyword::factory()->count(10)->create([
            'project_id' => $project->id,
        ]);
    }
}
