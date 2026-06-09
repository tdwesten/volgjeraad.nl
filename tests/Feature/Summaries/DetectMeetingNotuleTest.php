<?php

use App\Actions\Summaries\DetectMeetingNotule;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\StrayRequestException;

uses(RefreshDatabase::class);

test('an unfaked notule detection is blocked by the hermetic guard and fails hard', function (): void {
    // Bewust GEEN NotuleDetectionAgent::fake(): de hermetische suite
    // (Http::preventStrayRequests) moet dit hard laten falen i.p.v. een echte
    // OpenAI-call te doen of stilletjes 'geen notule' te concluderen.
    [$meeting] = meetingWithDocs();

    expect(fn () => app(DetectMeetingNotule::class)->handle($meeting))
        ->toThrow(StrayRequestException::class);
});

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

test('ignores a media_object_id that is not among the meetings documents', function (): void {
    [$meeting] = meetingWithDocs();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => true,
        'media_object_id' => 999999, // hoort niet bij deze meeting
        'confidence' => 90,
    ]]);

    app(DetectMeetingNotule::class)->handle($meeting);

    // Presence boven de drempel → notule wél vastgelegd, maar het foute id verworpen.
    expect($meeting->fresh()->notule_detected_at)->not->toBeNull();
    expect($meeting->fresh()->notule_media_object_id)->toBeNull();
});

test('marks notule_checked_at after an attempt even when nothing is found', function (): void {
    [$meeting] = meetingWithDocs();
    NotuleDetectionAgent::fake([[
        'is_notule_present' => false,
        'media_object_id' => null,
        'confidence' => 10,
    ]]);

    app(DetectMeetingNotule::class)->handle($meeting);

    expect($meeting->fresh()->notule_checked_at)->not->toBeNull();
    expect($meeting->fresh()->notule_detected_at)->toBeNull();
});

test('document payload includes the date from raw_payload when available', function (): void {
    $media = new MediaObject(['name' => 'Besluitenlijst', 'file_name' => 'besluit.pdf']);
    $media->id = 5;
    $media->raw_payload = ['date' => '2026-05-01'];

    $doc = DetectMeetingNotule::documentPayload($media);

    expect($doc['date'])->toBe('2026-05-01');
    expect($doc['id'])->toBe(5);
});

test('document payload omits the date when raw_payload has none', function (): void {
    $media = new MediaObject(['name' => 'Agenda', 'file_name' => 'agenda.pdf']);
    $media->id = 6;

    $doc = DetectMeetingNotule::documentPayload($media);

    expect($doc)->not->toHaveKey('date');
});
