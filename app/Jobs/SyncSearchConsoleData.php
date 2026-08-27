<?php

declare(strict_types=1);

namespace App\Jobs;

use App\GoogleSearchConsole\GoogleSearchConsoleClient;
use App\GoogleSearchConsole\GoogleSearchConsoleException;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Job diario (sección 6 del SPEC — no listada ahí, se agrega en la
 * Fase 2 igual que CalculateSerpCompetitors y DetectRankingAlerts):
 * sincroniza datos de Search Console para cada proyecto conectado.
 * Costo cero — "datos gratis" (sección 5 del SPEC).
 *
 * Pide una ventana de los últimos 7 días, no solo "ayer": los datos de
 * Search Console se revisan/finalizan varios días después de aparecer
 * por primera vez, así que hay que volver a pedir la ventana completa
 * y hacer upsert para que las cifras converjan a los valores finales
 * (mismo criterio de "última verdad gana" que ProcessDataForSeoPostback
 * con updateOrCreate).
 */
class SyncSearchConsoleData implements ShouldQueue
{
    use Queueable;

    private const SYNC_WINDOW_DAYS = 7;

    private const DIMENSIONS = ['date', 'query'];

    /**
     * Inyección por método, no por constructor: este job se programa
     * con `new SyncSearchConsoleData` en routes/console.php (igual que
     * ScheduleRankTrackingTasks), así que su constructor no puede
     * exigir un servicio no serializable como argumento.
     */
    public function handle(GoogleSearchConsoleClient $client): void
    {
        SearchConsoleConnection::query()
            ->each(fn (SearchConsoleConnection $connection) => $this->syncFor($client, $connection));
    }

    private function syncFor(GoogleSearchConsoleClient $client, SearchConsoleConnection $connection): void
    {
        $accessToken = $this->validAccessToken($client, $connection);

        if ($accessToken === null) {
            return;
        }

        $startDate = Carbon::now()->subDays(self::SYNC_WINDOW_DAYS)->toDateString();
        $endDate = Carbon::now()->toDateString();

        try {
            $rows = $client->querySearchAnalytics(
                $accessToken,
                $connection->site_url,
                $startDate,
                $endDate,
                self::DIMENSIONS,
            );
        } catch (GoogleSearchConsoleException $exception) {
            Log::warning('gsc.sync.query_failed', [
                'project_id' => $connection->project_id,
                'message' => $exception->getMessage(),
            ]);

            return;
        }

        foreach ($rows as $row) {
            [$date, $query] = $row['keys'];

            SearchConsoleMetric::query()->updateOrCreate(
                [
                    'project_id' => $connection->project_id,
                    'date' => $date,
                    'query' => $query,
                ],
                [
                    'clicks' => (int) $row['clicks'],
                    'impressions' => (int) $row['impressions'],
                    'ctr' => $row['ctr'],
                    'position' => $row['position'],
                ],
            );
        }
    }

    private function validAccessToken(GoogleSearchConsoleClient $client, SearchConsoleConnection $connection): ?string
    {
        if (! $connection->isExpired()) {
            return $connection->access_token;
        }

        try {
            $tokenData = $client->refreshAccessToken($connection->refresh_token);
        } catch (GoogleSearchConsoleException $exception) {
            Log::warning('gsc.sync.refresh_failed', [
                'project_id' => $connection->project_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        $connection->update([
            'access_token' => $tokenData['access_token'],
            'expires_at' => Carbon::now()->addSeconds($tokenData['expires_in']),
        ]);

        return $tokenData['access_token'];
    }
}
