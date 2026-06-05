<?php

use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'volgjeraad.youtube.max_transcript_attempts' => 4,
        'volgjeraad.youtube.transcript_wait_days' => 7,
    ]);
});

test('a non-council meeting is always resolved (no transcript expected)', function (): void {
    $meeting = Meeting::factory()->create(['type' => 'committee', 'starts_at' => now()]);

    expect($meeting->transcriptResolved())->toBeTrue();
});

test('a council meeting with a transcribed video is resolved', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()]);
    MeetingVideo::factory()->transcribed()->create(['meeting_id' => $meeting->id]);

    expect($meeting->fresh()->transcriptResolved())->toBeTrue();
});

test('a council meeting within the wait window without a transcript is not resolved', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDays(2)]);

    expect($meeting->transcriptResolved())->toBeFalse();
});

test('a council meeting is resolved once the wait window elapses without a transcript', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDays(8)]);

    expect($meeting->transcriptResolved())->toBeTrue();
});

test('a council meeting with a failed transcript at the attempt limit is resolved', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDay()]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 4,
    ]);

    expect($meeting->fresh()->transcriptResolved())->toBeTrue();
});

test('a council meeting with a failed transcript under the limit keeps waiting', function (): void {
    $meeting = Meeting::factory()->council()->create(['starts_at' => now()->subDay()]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'status' => VideoStatus::Failed->value,
        'transcript_attempts' => 1,
    ]);

    expect($meeting->fresh()->transcriptResolved())->toBeFalse();
});
