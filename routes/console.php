<?php

use App\Jobs\CalculateProjectVisibility;
use App\Jobs\GenerateScheduledReports;
use App\Jobs\ReconcilePendingTasks;
use App\Jobs\ScheduleRankTrackingTasks;
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
