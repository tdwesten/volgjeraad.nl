<?php

use App\Jobs\IngestMunicipalityMeetingsJob;
use App\Jobs\MatchMeetingVideosJob;
use App\Models\Municipality;
use App\Models\MunicipalityRequest;
use Illuminate\Support\Facades\Schedule;

// Item 15 — ORI-ingest per actieve gemeente, elke 15 minuten
Schedule::call(function () {
    Municipality::active()->each(
        fn (
            Municipality $municipality,
        ) => IngestMunicipalityMeetingsJob::dispatch($municipality->id),
    );
})
    ->everyFifteenMinutes()
    ->name('volgjeraad:ingest')
    ->withoutOverlapping();

Schedule::job(new MatchMeetingVideosJob)
    ->dailyAt('06:30')
    ->name('volgjeraad:match-videos')
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [MunicipalityRequest::class]])
    ->daily()
    ->name('volgjeraad:prune-municipality-requests');
