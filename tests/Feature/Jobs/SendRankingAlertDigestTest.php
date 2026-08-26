<?php

declare(strict_types=1);

use App\Jobs\SendRankingAlertDigest;
use App\Mail\RankingAlertDigestMail;
use App\Models\Keyword;
use App\Models\Project;
use App\Models\RankingAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

test('envia el resumen a los usuarios asignados y marca notified_at solo en las de hoy', function () {
    Mail::fake();

    $project = Project::factory()->create();
    $user1 = User::factory()->create(['email' => 'analista1@ingenio.la']);
    $user2 = User::factory()->create(['email' => 'analista2@ingenio.la']);
    $project->users()->attach([$user1->id, $user2->id]);

    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $todayAlert = RankingAlert::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'triggered_at' => Carbon::today()->toDateString(),
    ]);
    $oldAlert = RankingAlert::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'triggered_at' => Carbon::yesterday()->toDateString(),
        'notified_at' => Carbon::yesterday(),
    ]);

    (new SendRankingAlertDigest($project->id))->handle();

    Mail::assertSent(RankingAlertDigestMail::class, function (RankingAlertDigestMail $mail) use ($todayAlert) {
        return $mail->hasTo('analista1@ingenio.la')
            && $mail->hasTo('analista2@ingenio.la')
            && $mail->alerts->pluck('id')->all() === [$todayAlert->id];
    });

    expect($todayAlert->fresh()->notified_at)->not->toBeNull()
        ->and($oldAlert->fresh()->notified_at->toDateString())->toBe(Carbon::yesterday()->toDateString());
});

test('no envia nada si no hay alertas nuevas hoy', function () {
    Mail::fake();

    $project = Project::factory()->create();
    $project->users()->attach(User::factory()->create());

    (new SendRankingAlertDigest($project->id))->handle();

    Mail::assertNothingSent();
});

test('no envia nada si el proyecto no tiene usuarios asignados', function () {
    Mail::fake();
    Log::spy();

    $project = Project::factory()->create();
    $keyword = Keyword::factory()->create(['project_id' => $project->id]);
    $alert = RankingAlert::factory()->create([
        'project_id' => $project->id,
        'keyword_id' => $keyword->id,
        'triggered_at' => Carbon::today()->toDateString(),
    ]);

    (new SendRankingAlertDigest($project->id))->handle();

    Mail::assertNothingSent();
    Log::shouldHaveReceived('warning')->once();
    expect($alert->fresh()->notified_at)->toBeNull();
});
