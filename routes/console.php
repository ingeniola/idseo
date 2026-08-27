<?php

use App\Jobs\CalculateProjectVisibility;
use App\Jobs\CalculateSerpCompetitors;
use App\Jobs\DetectRankingAlerts;
use App\Jobs\GenerateScheduledReports;
use App\Jobs\ReconcilePendingTasks;
use App\Jobs\ScheduleRankTrackingTasks;
use App\Jobs\SyncSearchConsoleData;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sección 6 del SPEC.
Schedule::job(new ScheduleRankTrackingTasks)->dailyAt('02:00');
Schedule::job(new ReconcilePendingTasks)->hourly();
Schedule::job(new CalculateProjectVisibility)->dailyAt('06:00');
// Fase 2: análisis de competidores. Después de las 06:00 porque no
// depende de CalculateProjectVisibility, solo comparte el horario de
// "derivados diarios, sin costo de API".
Schedule::job(new CalculateSerpCompetitors)->dailyAt('06:15');
// Fase 2: alertas. 06:30 según la tabla de la sección 6 del SPEC —
// después de CalculateSerpCompetitors para no competir por recursos a
// la misma hora exacta.
Schedule::job(new DetectRankingAlerts)->dailyAt('06:30');
// Fase 2: Search Console. 06:45, después de las alertas — no depende
// de ningún otro job "derivado diario", solo comparte el bloque
// horario. Costo cero (datos gratis).
Schedule::job(new SyncSearchConsoleData)->dailyAt('06:45');
Schedule::job(new GenerateScheduledReports)->monthlyOn(1, '07:00');

/**
 * Respaldos automáticos diarios (sección 9 y Fase 1 paso 12 del SPEC).
 * clean antes de run: por si un respaldo previo se quedó a medias,
 * no cuenta contra la retención del de hoy. monitor corre al final
 * para chequear el resultado de este mismo run, no el de ayer.
 * Horario antes de que arranque el resto de la programación diaria
 * (02:00) para no competir por recursos con ScheduleRankTrackingTasks.
 */
Schedule::command('backup:clean')->dailyAt('00:30');
Schedule::command('backup:run')->dailyAt('01:00');
Schedule::command('backup:monitor')->dailyAt('01:30');
