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
