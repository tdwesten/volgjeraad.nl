<?php

use App\Actions\Meetings\RegenerateMeeting;
use App\Jobs\IngestMeetingAgendaJob;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\ProcessingLog;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('admin can regenerate a meeting and summaries are deleted', function (): void {
    Bus::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);

    Summary::factory()->create([
        'meeting_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
    ]);

    Newsletter::factory()->create([
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    // Agendapunt + media: regenerate moet deze verwijderen (cascade), niet
    // de NOT NULL raw_payload_hash op null zetten.
    $agendaItem = AgendaItem::factory()->create(['meeting_id' => $meeting->id]);
    MediaObject::factory()->create(['agenda_item_id' => $agendaItem->id]);

    $this->actingAs($admin)
        ->post("/admin/review/{$meeting->id}/regenerate")
        ->assertRedirect("/admin/municipalities/{$municipality->id}/meetings/{$meeting->id}")
        ->assertSessionHas('success', 'Vergadering wordt opnieuw verwerkt.');

    expect($meeting->fresh()->summaries()->count())->toBe(0);
    expect($meeting->fresh()->agendaItems()->count())->toBe(0);
    expect(MediaObject::where('agenda_item_id', $agendaItem->id)->exists())->toBeFalse();
    expect(Newsletter::where('meeting_id', $meeting->id)->exists())->toBeFalse();
    expect($meeting->fresh()->summarized_at)->toBeNull();
    expect($meeting->fresh()->agenda_ingested_at)->toBeNull();

    Bus::assertDispatched(IngestMeetingAgendaJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('regenerate resets the source-resolution fields', function (): void {
    Bus::fake();

    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'summary_source' => 'transcript',
        'summary_skipped_reason' => 'no_source',
        'notule_detected_at' => now(),
        'notule_checked_at' => now(),
    ]);
    $agendaItem = AgendaItem::factory()->create(['meeting_id' => $meeting->id]);
    $media = MediaObject::factory()->create(['agenda_item_id' => $agendaItem->id]);
    $meeting->update(['notule_media_object_id' => $media->id]);

    app(RegenerateMeeting::class)->handle($meeting);

    $fresh = $meeting->fresh();
    expect($fresh->summary_source)->toBeNull();
    expect($fresh->summary_skipped_reason)->toBeNull();
    expect($fresh->notule_detected_at)->toBeNull();
    expect($fresh->notule_checked_at)->toBeNull();
    expect($fresh->notule_media_object_id)->toBeNull();
});

test('regenerate creates a processing log entry', function (): void {
    Bus::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $this->actingAs($admin)->post("/admin/review/{$meeting->id}/regenerate");

    $log = ProcessingLog::where('meeting_id', $meeting->id)->first();
    expect($log)->not->toBeNull();
    expect($log->step)->toBe('regenerate');
    expect($log->status)->toBe('info');
});

test('non-admin gets 403 on regenerate', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $this->actingAs($user)
        ->post("/admin/review/{$meeting->id}/regenerate")
        ->assertForbidden();
});

test('unauthenticated user is redirected from regenerate', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $this->post("/admin/review/{$meeting->id}/regenerate")
        ->assertRedirect('/login');
});

test('the old review detail route redirects to the new admin meeting page', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $this->actingAs($admin)
        ->get("/admin/review/{$meeting->id}")
        ->assertRedirect(route('admin.municipalities.meetings.show', [$municipality, $meeting]));
});
