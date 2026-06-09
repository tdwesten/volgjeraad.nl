<?php

use App\Enums\MeetingProcessingStatus;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use App\Models\ProcessingLog;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('non-admin gets 403 on the admin meeting page', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $muni = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $muni->id]);

    $this->actingAs($user)
        ->get(route('admin.municipalities.meetings.show', [$muni, $meeting]))
        ->assertForbidden();
});

test('a meeting that does not belong to the municipality returns 404', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $a = Municipality::factory()->create();
    $b = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $a->id]);

    $this->actingAs($admin)
        ->get(route('admin.municipalities.meetings.show', [$b, $meeting]))
        ->assertNotFound();
});

test('the admin meeting page exposes status, draft summaries, sources and logs', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDays(3),
        'summarized_at' => now(),
        'summary_source' => 'notule',
    ]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'attachments_fetched_at' => now()]);
    $notule = MediaObject::factory()->create([
        'agenda_item_id' => $item->id,
        'name' => 'Besluitenlijst 3 juni 2026',
        'url' => 'https://ori.example/doc',
        'original_url' => 'https://ori.example/doc.pdf',
    ]);
    $meeting->update(['notule_media_object_id' => $notule->id]);

    // Draft-samenvatting (mag op de beheerpagina zichtbaar zijn).
    Summary::factory()->create([
        'summarizable_type' => $meeting->getMorphClass(),
        'summarizable_id' => $meeting->id,
        'meeting_id' => $meeting->id,
        'municipality_id' => $muni->id,
        'level' => 'standard',
        'status' => 'draft',
        'title' => 'Concept titel',
        'body' => 'Concept inhoud',
    ]);

    ProcessingLog::factory()->forMeeting($meeting)->create([
        'step' => 'resolve',
        'status' => 'success',
        'message' => 'Bron geresolveerd',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.municipalities.meetings.show', [$muni, $meeting]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Meetings/Show')
            ->where('meeting.processing_status', MeetingProcessingStatus::InReview->value)
            ->where('meeting.processing_label', MeetingProcessingStatus::InReview->adminLabel())
            ->where('meeting.municipality.id', $muni->id)
            ->where('sources.summary_source', 'notule')
            ->where('sources.notule.name', 'Besluitenlijst 3 juni 2026')
            ->where('sources.notule.original_url', 'https://ori.example/doc.pdf')
            ->where('standardSummary.title', 'Concept titel')
            ->where('standardSummary.status', 'draft')
            ->where('simpleSummary', null)
            ->has('agendaItems', 1)
            ->has('agendaItems.0.mediaObjects', 1)
            ->has('logs', 1)
        );
});

test('the admin meeting page exposes the video and transcript indication', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $muni = Municipality::factory()->create(['launch_date' => now()->subYear()]);
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $muni->id,
        'starts_at' => now()->subDays(3),
        'summary_source' => 'transcript',
    ]);
    MeetingVideo::factory()->transcribed()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.municipalities.meetings.show', [$muni, $meeting]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('video.status', 'transcribed')
            ->where('video.has_transcript', true)
            ->where('video.youtube_video_id', 'dQw4w9WgXcQ')
            ->where('sources.has_transcript', true)
            ->where('sources.has_video', true)
            ->where('sources.summary_source', 'transcript')
        );
});
