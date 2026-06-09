<?php

use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public meeting page exposes a status message when there is no published summary', function (): void {
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDays(10),
        'summary_skipped_reason' => 'no_source',
    ]);

    $this->get(route('meeting.show', [$muni, $meeting]))
        ->assertInertia(fn ($page) => $page
            ->where('meeting.processing_status', 'no_source')
            ->where('meeting.status_message', 'Geen samenvatting: er is geen besluitenlijst beschikbaar.'));
});
