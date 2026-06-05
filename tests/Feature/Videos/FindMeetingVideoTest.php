<?php

use App\Actions\Videos\FindMeetingVideo;
use App\Ai\Agents\VideoMatchAgent;
use App\Enums\VideoStatus;
use App\Http\Integrations\YouTube\Requests\SearchChannelVideosRequest;
use App\Http\Integrations\YouTube\YouTubeConnector;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;

uses(RefreshDatabase::class);

function makeCouncilMeetingWithChannel(): Meeting
{
    $municipality = Municipality::factory()->create([
        'settings' => ['youtube_channel_id' => 'UC_brummen'],
    ]);

    return Meeting::factory()->council()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Raadsvergadering 4 juni 2026',
        'starts_at' => '2026-06-04 19:00:00',
    ]);
}

/**
 * Bind een YouTubeConnector met een MockClient in de container, zodat de
 * container-resolved YouTubeClient dezelfde fake gebruikt (ORI-patroon, review MINOR).
 *
 * @param  array<string, mixed>  $response
 */
function bindFakeYouTube(array $response): void
{
    $mockClient = new MockClient([
        SearchChannelVideosRequest::class => MockResponse::make($response, 200),
    ]);

    app()->bind(YouTubeConnector::class, function () use ($mockClient): YouTubeConnector {
        $connector = new YouTubeConnector;
        $connector->withMockClient($mockClient);

        return $connector;
    });
}

function oneCandidateResponse(): array
{
    return [
        'items' => [
            [
                'id' => ['videoId' => 'dQw4w9WgXcQ'],
                'snippet' => [
                    'title' => 'Raadsvergadering 4 juni 2026',
                    'publishedAt' => '2026-06-04T21:00:00Z',
                ],
            ],
        ],
    ];
}

test('high confidence with a known video_id auto-matches and confirms', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([[
        'video_id' => 'dQw4w9WgXcQ',
        'confidence' => 90,
        'reason' => 'Titel en datum komen overeen.',
    ]]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video)->not->toBeNull();
    expect($video->status)->toBe(VideoStatus::Matched);
    expect($video->youtube_video_id)->toBe('dQw4w9WgXcQ');
    expect($video->match_confidence)->toBe(90);
    expect($video->confirmed_at)->not->toBeNull();
    expect($video->candidates)->toHaveCount(1);
});

test('low confidence stores needs_confirmation without confirming', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([[
        'video_id' => 'dQw4w9WgXcQ',
        'confidence' => 40,
        'reason' => 'Onzeker; titel wijkt af.',
    ]]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NeedsConfirmation);
    expect($video->confirmed_at)->toBeNull();
    expect($video->match_confidence)->toBe(40);
});

test('agent choosing an unknown video_id never auto-matches', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([[
        'video_id' => 'HALLUCINATED99',
        'confidence' => 99,
        'reason' => 'Hoge confidence maar verzonnen id.',
    ]]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NeedsConfirmation);
    expect($video->youtube_video_id)->toBeNull();
});

test('no candidates stores not_found and increments attempts', function (): void {
    bindFakeYouTube(['items' => []]);
    VideoMatchAgent::fake([]);

    $meeting = makeCouncilMeetingWithChannel();
    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video->status)->toBe(VideoStatus::NotFound);
    expect($video->match_attempts)->toBe(1);
    expect($video->youtube_video_id)->toBeNull();
});

test('missing channel id returns null, logs a warning and creates no video', function (): void {
    VideoMatchAgent::fake([]);
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $msg === 'find_meeting_video missing channel id' && isset($ctx['meeting_id']));

    $municipality = Municipality::factory()->create(['settings' => null]);
    $meeting = Meeting::factory()->council()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'starts_at' => '2026-06-04 19:00:00',
    ]);

    $video = app(FindMeetingVideo::class)->handle($meeting);

    expect($video)->toBeNull();
    expect($meeting->fresh()->video)->toBeNull();
});

test('repeated find on same meeting updates the single video row', function (): void {
    bindFakeYouTube(oneCandidateResponse());
    VideoMatchAgent::fake([
        ['video_id' => 'dQw4w9WgXcQ', 'confidence' => 40, 'reason' => 'Onzeker.'],
        ['video_id' => 'dQw4w9WgXcQ', 'confidence' => 90, 'reason' => 'Nu zeker.'],
    ]);

    $meeting = makeCouncilMeetingWithChannel();
    $action = app(FindMeetingVideo::class);

    $action->handle($meeting);
    $action->handle($meeting);

    expect($meeting->fresh()->video->status)->toBe(VideoStatus::Matched);
    expect($meeting->fresh()->video->match_attempts)->toBe(2);
    expect(MeetingVideo::where('meeting_id', $meeting->id)->count())->toBe(1);
});
