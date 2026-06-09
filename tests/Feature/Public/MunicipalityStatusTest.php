<?php

use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public list includes a past meeting without a summary, with a public message', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'name' => 'Raad zonder besluitenlijst',
        'starts_at' => now()->subDays(10),
        'summary_skipped_reason' => 'no_source',
    ]);

    $this->get(route('municipality.show', $muni))
        ->assertInertia(fn ($page) => $page
            ->where('meetings.0.processing_status', 'no_source')
            ->where('meetings.0.status_message', 'Geen samenvatting: er is geen besluitenlijst beschikbaar.'));
});

test('the public list hides future meetings', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->addDays(3),
    ]);

    $this->get(route('municipality.show', $muni))
        ->assertInertia(fn ($page) => $page->where('meetings', []));
});
