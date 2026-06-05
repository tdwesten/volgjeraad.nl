<?php

use App\Enums\VideoStatus;
use App\Jobs\ProcessMeetingVideoJob;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('index lists needs_confirmation videos for an admin', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = Meeting::factory()->summarizable()->create(['name' => 'Raadsvergadering 4 juni']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'aaa11111111', 'title' => 'Raad A', 'publishedAt' => '2026-06-04T19:00:00+00:00']],
    ]);

    $this->actingAs($admin)
        ->get('/admin/videos')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/Videos/Index')
            ->has('videos', 1)
            ->where('videos.0.meeting.name', 'Raadsvergadering 4 juni')
            ->has('videos.0.candidates', 1));
});

test('non-admin cannot access the video review queue', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/videos')->assertForbidden();
});

test('confirm endpoint matches the chosen candidate and dispatches processing', function (): void {
    Bus::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'bbb22222222', 'title' => 'Raad B', 'publishedAt' => '2026-06-04T20:00:00+00:00']],
    ]);

    $this->actingAs($admin)
        ->post("/admin/videos/{$video->id}/confirm", ['video_id' => 'bbb22222222'])
        ->assertRedirect('/admin/videos');

    expect($video->fresh()->status)->toBe(VideoStatus::Matched);
    Bus::assertDispatched(ProcessMeetingVideoJob::class, fn ($job) => $job->meetingId === $meeting->id);
});

test('confirm rejects a video_id outside the candidate list', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = Meeting::factory()->summarizable()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::NeedsConfirmation->value,
        'candidates' => [['videoId' => 'bbb22222222', 'title' => 'Raad B', 'publishedAt' => '2026-06-04T20:00:00+00:00']],
    ]);

    $this->actingAs($admin)
        ->post("/admin/videos/{$video->id}/confirm", ['video_id' => 'zzz99999999'])
        ->assertSessionHasErrors('video_id');

    expect($video->fresh()->status)->toBe(VideoStatus::NeedsConfirmation);
});
