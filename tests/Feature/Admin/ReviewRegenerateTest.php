<?php

use App\Jobs\IngestMeetingAgendaJob;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\ProcessingLog;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia as Assert;

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

    $this->actingAs($admin)
        ->post("/admin/review/{$meeting->id}/regenerate")
        ->assertRedirect('/admin/review')
        ->assertSessionHas('success', 'Vergadering wordt opnieuw verwerkt.');

    expect($meeting->fresh()->summaries()->count())->toBe(0);
    expect(Newsletter::where('meeting_id', $meeting->id)->exists())->toBeFalse();
    expect($meeting->fresh()->summarized_at)->toBeNull();
    expect($meeting->fresh()->agenda_ingested_at)->toBeNull();

    Bus::assertDispatched(IngestMeetingAgendaJob::class, fn ($job) => $job->meetingId === $meeting->id);
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

test('review show passes processing logs to inertia', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    ProcessingLog::factory()->forMeeting($meeting)->create([
        'step' => 'agenda',
        'status' => 'success',
        'message' => 'Agenda opgehaald',
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/review/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Review/Show')
            ->has('logs', 1)
            ->where('logs.0.step', 'agenda')
            ->where('logs.0.status', 'success')
        );
});

test('review show works without a newsletter when processing logs exist', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    ProcessingLog::factory()->forMeeting($meeting)->create(['step' => 'regenerate', 'status' => 'info', 'message' => 'Handmatig opnieuw verwerken gestart']);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/review/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('newsletter', null)
            ->has('logs', 1)
        );
});
