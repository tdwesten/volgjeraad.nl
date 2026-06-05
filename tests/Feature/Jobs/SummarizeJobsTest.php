<?php

use App\Actions\Summaries\GenerateAgendaItemSummary;
use App\Actions\Summaries\GenerateMeetingSummary;
use App\Ai\Agents\AgendaSummaryAgent;
use App\Ai\Agents\MeetingSummaryAgent;
use App\Enums\SummaryLevel;
use App\Jobs\ComposeNewsletterJob;
use App\Jobs\SummarizeAgendaItemJob;
use App\Jobs\SummarizeMeetingJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

test('SummarizeAgendaItemJob calls GenerateAgendaItemSummary action', function (): void {
    AgendaSummaryAgent::fake([[
        'title' => 'Test', 'body' => 'Body', 'impact_note' => 'Note', 'confidence' => 80, 'flags' => [],
    ]]);

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id]);

    $action = Mockery::mock(GenerateAgendaItemSummary::class);
    $action->shouldReceive('handle')->once()->with(
        Mockery::on(fn ($i) => $i->id === $item->id),
        SummaryLevel::Standard,
    );
    app()->instance(GenerateAgendaItemSummary::class, $action);

    $job = new SummarizeAgendaItemJob($item->id, SummaryLevel::Standard);
    $job->handle(app(GenerateAgendaItemSummary::class));
});

test('SummarizeMeetingJob dispatches ComposeNewsletterJob when all summary levels exist', function (): void {
    Bus::fake([ComposeNewsletterJob::class]);

    MeetingSummaryAgent::fake([[
        'title' => 'Meeting summary', 'body' => 'Body', 'impact_note' => '', 'confidence' => 90, 'flags' => [],
    ]]);

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    // Pre-create summary for Standard level so after Simple is done both exist
    Summary::create([
        'summarizable_type' => $meeting->getMorphClass(),
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'level' => SummaryLevel::Standard->value,
        'language' => 'nl',
        'source_hash' => 'abc',
        'status' => 'draft',
        'title' => 'Standard',
        'body' => 'Body',
        'input_tokens' => 0,
        'output_tokens' => 0,
        'prompt_version' => 'v1',
        'model' => 'gpt-4o-mini',
    ]);

    $action = app(GenerateMeetingSummary::class);
    $job = new SummarizeMeetingJob($meeting->id, SummaryLevel::Simple);
    $job->handle($action);

    Bus::assertDispatched(ComposeNewsletterJob::class, fn ($j) => $j->meetingId === $meeting->id);
});

test('SummarizeMeetingJob does not dispatch ComposeNewsletterJob when not all levels done', function (): void {
    Bus::fake([ComposeNewsletterJob::class]);

    MeetingSummaryAgent::fake([[
        'title' => 'Meeting summary', 'body' => 'Body', 'impact_note' => '', 'confidence' => 90, 'flags' => [],
    ]]);

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    // Only Standard done after this job; Simple not pre-created

    $action = app(GenerateMeetingSummary::class);
    $job = new SummarizeMeetingJob($meeting->id, SummaryLevel::Standard);
    $job->handle($action);

    Bus::assertNotDispatched(ComposeNewsletterJob::class);
});
