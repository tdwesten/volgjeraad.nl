<?php

use App\Jobs\IngestMunicipalityMeetingsJob;
use App\Jobs\MatchMeetingVideosJob;
use App\Models\Municipality;
use App\Models\MunicipalityRequest;
use Illuminate\Support\Facades\Schedule;

// Item 15 — dagelijkse ORI-ingest per actieve gemeente
Schedule::call(function () {
    Municipality::active()->each(
        fn (
            Municipality $municipality,
        ) => IngestMunicipalityMeetingsJob::dispatch($municipality->id),
    );
})
    ->dailyAt('06:00')
    ->name('volgjeraad:daily-ingest')
    ->withoutOverlapping();

Schedule::job(new MatchMeetingVideosJob)
    ->dailyAt('06:30')
    ->name('volgjeraad:match-videos')
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [MunicipalityRequest::class]])
    ->daily()
    ->name('volgjeraad:prune-municipality-requests');
