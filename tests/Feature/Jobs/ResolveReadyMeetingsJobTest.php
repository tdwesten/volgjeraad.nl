<?php

use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Jobs\ResolveReadyMeetingsJob;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

test('it resolves summarizable meetings that have taken place and are unresolved', function (): void {
    $eligible = Meeting::factory()->summarizable()->create(['starts_at' => now()->subDay()]);
    Meeting::factory()->summarizable()->create(['starts_at' => now()->addDay()]);           // toekomst
    Meeting::factory()->summarizable()->create(['starts_at' => now()->subDay(), 'summarized_at' => now()]); // al klaar
    Meeting::factory()->summarizable()->create(['starts_at' => now()->subDay(), 'summary_skipped_reason' => 'no_source']); // skip
    Meeting::factory()->create(['ingest_mode' => 'metadata_only', 'starts_at' => now()->subDay()]); // niet-summarizable

    $resolver = Mockery::mock(ResolveMeetingSummarySources::class);
    $resolver->shouldReceive('handle')->once()->with(Mockery::on(fn (Meeting $m) => $m->id === $eligible->id));
    app()->instance(ResolveMeetingSummarySources::class, $resolver);

    (new ResolveReadyMeetingsJob)->handle($resolver);
});
