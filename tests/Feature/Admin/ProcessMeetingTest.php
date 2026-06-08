<?php

use App\Enums\IngestMode;
use App\Jobs\IngestMeetingAgendaJob;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('forces summarize mode and dispatches the pipeline for an admin', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->for($municipality)->create([
        'ingest_mode' => IngestMode::MetadataOnly->value,
        'summarized_at' => now(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.municipalities.process-meeting', [$municipality, $meeting]))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($meeting->fresh()->ingest_mode)->toBe(IngestMode::Summarize);
    expect($meeting->fresh()->summarized_at)->toBeNull();

    Queue::assertPushed(IngestMeetingAgendaJob::class, fn ($job) => $job->meetingId === $meeting->id);
    Queue::assertPushed(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

it('returns 404 when the meeting belongs to another municipality', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $other = Municipality::factory()->create();
    $meeting = Meeting::factory()->for($other)->create();

    $this->actingAs($admin)
        ->post(route('admin.municipalities.process-meeting', [$municipality, $meeting]))
        ->assertNotFound();

    Queue::assertNothingPushed();
});

it('forbids non-admins from starting processing', function () {
    Queue::fake();

    $user = User::factory()->create(['is_admin' => false]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->for($municipality)->create();

    $this->actingAs($user)
        ->post(route('admin.municipalities.process-meeting', [$municipality, $meeting]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});
