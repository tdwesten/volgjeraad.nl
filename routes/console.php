<?php

use App\Jobs\IngestMunicipalityMeetingsJob;
use App\Jobs\ResolveReadyMeetingsJob;
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

Schedule::job(new ResolveReadyMeetingsJob)
    ->everyFifteenMinutes()
    ->name('volgjeraad:resolve')
    ->withoutOverlapping();

Schedule::command('model:prune', ['--model' => [MunicipalityRequest::class]])
    ->daily()
    ->name('volgjeraad:prune-municipality-requests');
