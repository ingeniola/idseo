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

/**
 * ->sentryMonitor('slug') en cada tarea (sección 12 del SPEC: "alerta
 * si el scheduler deja de correr"). Manda un check-in a Sentry Crons
 * al empezar y al terminar cada corrida; si un check-in esperado no
 * llega (el scheduler dejó de correr, el proceso murió, un deploy
 * rompió el cron del servidor), Sentry avisa — no depende de que la
 * propia app detecte su propia ausencia, que es el problema de fondo
 * de cualquier heartbeat "desde adentro". Sin efecto si
 * SENTRY_LARAVEL_DSN no está configurado. El slug es el nombre del
 * monitor en Sentry; el plan gratuito de Sentry tiene un límite de
 * monitores de cron — confirmar contra el plan real antes de asumir
 * que todos caben.
 */

// Sección 6 del SPEC.
Schedule::job(new ScheduleRankTrackingTasks)->dailyAt('02:00')->sentryMonitor('schedule-rank-tracking-tasks');
Schedule::job(new ReconcilePendingTasks)->hourly()->sentryMonitor('reconcile-pending-tasks');
Schedule::job(new CalculateProjectVisibility)->dailyAt('06:00')->sentryMonitor('calculate-project-visibility');
// Fase 2: análisis de competidores. Después de las 06:00 porque no
// depende de CalculateProjectVisibility, solo comparte el horario de
// "derivados diarios, sin costo de API".
Schedule::job(new CalculateSerpCompetitors)->dailyAt('06:15')->sentryMonitor('calculate-serp-competitors');
// Fase 2: alertas. 06:30 según la tabla de la sección 6 del SPEC —
// después de CalculateSerpCompetitors para no competir por recursos a
// la misma hora exacta.
Schedule::job(new DetectRankingAlerts)->dailyAt('06:30')->sentryMonitor('detect-ranking-alerts');
// Fase 2: Search Console. 06:45, después de las alertas — no depende
// de ningún otro job "derivado diario", solo comparte el bloque
// horario. Costo cero (datos gratis).
Schedule::job(new SyncSearchConsoleData)->dailyAt('06:45')->sentryMonitor('sync-search-console-data');
Schedule::job(new GenerateScheduledReports)->monthlyOn(1, '07:00')->sentryMonitor('generate-scheduled-reports');

/**
 * Respaldos automáticos diarios (sección 9 y Fase 1 paso 12 del SPEC).
 * clean antes de run: por si un respaldo previo se quedó a medias,
 * no cuenta contra la retención del de hoy. monitor corre al final
 * para chequear el resultado de este mismo run, no el de ayer.
 * Horario antes de que arranque el resto de la programación diaria
 * (02:00) para no competir por recursos con ScheduleRankTrackingTasks.
 * Estas tres son las más críticas de monitorear: son la última línea
 * de defensa si el servidor completo se corrompe (sección 12 y riesgo
 * de la sección 9 — "un respaldo que nunca se restauró no es un
 * respaldo", pero uno que dejó de correr sin que nadie se entere es
 * peor todavía).
 */
Schedule::command('backup:clean')->dailyAt('00:30')->sentryMonitor('backup-clean');
Schedule::command('backup:run')->dailyAt('01:00')->sentryMonitor('backup-run');
Schedule::command('backup:monitor')->dailyAt('01:30')->sentryMonitor('backup-monitor');
