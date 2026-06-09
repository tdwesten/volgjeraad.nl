<?php

use App\Actions\Summaries\DetectMeetingNotule;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['volgjeraad.ai.notule_confidence_threshold' => 70]));

function meetingWithDocs(): array
{
    $meeting = Meeting::factory()->summarizable()->create(['agenda_ingested_at' => now()]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);
    $media = MediaObject::factory()->create([
        'agenda_item_id' => $item->id,
        'name' => 'Besluitenlijst 3 juni 2026',
    ]);

    return [$meeting->fresh(), $media];
}

test('stores the notule when the agent finds one above threshold', function (): void {
    [$meeting, $media] = meetingWithDocs();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => $media->id,
        'confidence' => 88,
    ]]);

    app(DetectMeetingNotule::class)->handle($meeting);

    expect($meeting->fresh()->notule_detected_at)->not->toBeNull();
    expect($meeting->fresh()->notule_media_object_id)->toBe($media->id);
});

test('does not store when below the confidence threshold', function (): void {
    [$meeting] = meetingWithDocs();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => null,
        'confidence' => 40,
    ]]);

    app(DetectMeetingNotule::class)->handle($meeting);

    expect($meeting->fresh()->notule_detected_at)->toBeNull();
});

test('is a no-op when a notule was already detected', function (): void {
    [$meeting, $media] = meetingWithDocs();
    $meeting->update(['notule_detected_at' => now()->subDay(), 'notule_media_object_id' => $media->id]);
    NotuleDetectionAgent::fake([['is_notule_present' => false, 'media_object_id' => null, 'confidence' => 0]]);

    app(DetectMeetingNotule::class)->handle($meeting->fresh());

    // detected_at blijft de oude waarde (agent niet bepalend)
    expect($meeting->fresh()->notule_media_object_id)->toBe($media->id);
});
