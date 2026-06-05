<?php

use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('meeting has one video and casts status and candidates', function (): void {
    $meeting = Meeting::factory()->create();
    $video = MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Matched->value,
        'candidates' => [['videoId' => 'abc', 'title' => 'Raadsvergadering']],
    ]);

    expect($meeting->fresh()->video)->not->toBeNull();
    expect($meeting->fresh()->video->id)->toBe($video->id);
    expect($video->status)->toBe(VideoStatus::Matched);
    expect($video->candidates)->toBe([['videoId' => 'abc', 'title' => 'Raadsvergadering']]);
});

test('new meeting video defaults to pending status and zero attempts', function (): void {
    $meeting = Meeting::factory()->create();
    $video = new MeetingVideo(['meeting_id' => $meeting->id]);
    $video->save();

    expect($video->status)->toBe(VideoStatus::Pending);
    expect($video->match_attempts)->toBe(0);
    expect($video->transcript_attempts)->toBe(0);
});
