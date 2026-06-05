<?php

use App\Actions\Summaries\GenerateMeetingSummary;
use App\Ai\Agents\MeetingSummaryAgent;
use App\Enums\SummaryLevel;
use App\Enums\VideoStatus;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use App\Support\PayloadHasher;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function agentFakeDefaults(): void
{
    MeetingSummaryAgent::fake([[
        'title' => 'Samenvatting', 'body' => 'Inhoud', 'impact_note' => null, 'confidence' => 85, 'flags' => [],
    ]]);
}

test('transcript block is included in source text sent to agent when video is Transcribed', function (): void {
    agentFakeDefaults();

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'position' => 1, 'name' => 'Agendapunt A']);
    MeetingVideo::factory()->transcribed()->create([
        'meeting_id' => $meeting->id,
        'transcript_text' => 'Dit is het transcript van de vergadering.',
    ]);

    $summary = app(GenerateMeetingSummary::class)->handle($meeting->fresh(), SummaryLevel::Standard);

    expect($summary)->not->toBeNull();
    expect($summary->source_hash)->toBe(
        PayloadHasher::hash(['text' => "=== BESLUITENLIJST + AGENDA ===\n\n\n\n=== TRANSCRIPT (debat) ===\n\nDit is het transcript van de vergadering."])
    );
});

test('transcript is absent from source_hash when video is not Transcribed', function (): void {
    agentFakeDefaults();

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'position' => 1, 'name' => 'Agendapunt A']);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Matched,
        'transcript_text' => null,
    ]);

    $summary = app(GenerateMeetingSummary::class)->handle($meeting->fresh(), SummaryLevel::Standard);

    expect($summary)->not->toBeNull();
    // source_hash should NOT contain transcript headers
    expect($summary->source_hash)->not->toBe(
        PayloadHasher::hash(['text' => "=== BESLUITENLIJST + AGENDA ===\n\n"])
    );
});

test('transcript is truncated to max_transcript_chars and source_truncated flag is set', function (): void {
    agentFakeDefaults();
    config(['volgjeraad.ai.max_transcript_chars' => 20]);

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'position' => 1]);
    MeetingVideo::factory()->transcribed()->create([
        'meeting_id' => $meeting->id,
        'transcript_text' => str_repeat('a', 100),
    ]);

    $summary = app(GenerateMeetingSummary::class)->handle($meeting->fresh(), SummaryLevel::Standard);

    expect($summary->flags)->toContain('source_truncated');
});

test('meeting without video and without agenda returns source_text_missing summary', function (): void {
    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $summary = app(GenerateMeetingSummary::class)->handle($meeting, SummaryLevel::Standard);

    expect($summary)->not->toBeNull();
    expect($summary->flags)->toContain('source_text_missing');
});

test('different transcript texts produce different source hashes preventing duplicate summaries', function (): void {
    agentFakeDefaults();

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);

    $meetingA = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    AgendaItem::factory()->create(['meeting_id' => $meetingA->id, 'position' => 1]);
    MeetingVideo::factory()->transcribed()->create([
        'meeting_id' => $meetingA->id,
        'transcript_text' => 'Transcript A',
    ]);

    $meetingB = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    AgendaItem::factory()->create(['meeting_id' => $meetingB->id, 'position' => 1]);
    MeetingVideo::factory()->transcribed()->create([
        'meeting_id' => $meetingB->id,
        'transcript_text' => 'Transcript B',
    ]);

    $summaryA = app(GenerateMeetingSummary::class)->handle($meetingA->fresh(), SummaryLevel::Standard);
    $summaryB = app(GenerateMeetingSummary::class)->handle($meetingB->fresh(), SummaryLevel::Standard);

    expect($summaryA->source_hash)->not->toBe($summaryB->source_hash);
});
