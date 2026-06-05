<?php

use App\Jobs\IngestMunicipalityMeetingsJob;
use App\Models\Municipality;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Item 15 — dagelijkse ORI-ingest per actieve gemeente
Schedule::call(function () {
    Municipality::active()->each(
        fn (Municipality $municipality) => IngestMunicipalityMeetingsJob::dispatch($municipality->id),
    );
})->dailyAt('06:00')->name('volgjeraad:daily-ingest')->withoutOverlapping();
