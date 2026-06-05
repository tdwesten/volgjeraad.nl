<?php

use App\Actions\Newsletters\ComposeNewsletter;
use App\Enums\NewsletterStatus;
use App\Enums\SummaryLevel;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function meetingWithSummaries(): Meeting
{
    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $item1 = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'position' => 1]);
    $item2 = AgendaItem::factory()->create(['meeting_id' => $meeting->id, 'position' => 2]);

    foreach ([$item1, $item2] as $item) {
        foreach (SummaryLevel::cases() as $level) {
            Summary::create([
                'summarizable_type' => $item->getMorphClass(),
                'summarizable_id' => $item->id,
                'municipality_id' => $municipality->id,
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'language' => 'nl',
                'source_hash' => 'hash-'.$item->id.'-'.$level->value,
                'status' => 'draft',
                'title' => 'Title '.$level->value,
                'body' => 'Body',
                'input_tokens' => 0,
                'output_tokens' => 0,
                'prompt_version' => 'v1',
                'model' => 'gpt-4o-mini',
            ]);
        }
    }

    return $meeting->fresh();
}

test('composes one draft newsletter per meeting', function (): void {
    $meeting = meetingWithSummaries();
    $action = new ComposeNewsletter;
    $newsletter = $action->handle($meeting);

    expect($newsletter->status)->toBe(NewsletterStatus::Draft);
    expect($newsletter->meeting_id)->toBe($meeting->id);
    expect($newsletter->summaries()->count())->toBe(4); // 2 items × 2 levels
});

test('is idempotent — second call updates existing newsletter', function (): void {
    $meeting = meetingWithSummaries();
    $action = new ComposeNewsletter;

    $first = $action->handle($meeting);
    $second = $action->handle($meeting);

    expect($first->id)->toBe($second->id);
    expect(Newsletter::count())->toBe(1);
});

test('summaries are attached in agenda position order', function (): void {
    $meeting = meetingWithSummaries();
    $action = new ComposeNewsletter;
    $newsletter = $action->handle($meeting);

    $positions = $newsletter->summaries()->orderByPivot('position')->pluck('newsletter_summary.position')->toArray();

    // Positions should be non-decreasing (agenda item order)
    for ($i = 1; $i < count($positions); $i++) {
        expect($positions[$i])->toBeGreaterThanOrEqual($positions[$i - 1]);
    }

    // Each agenda item's summaries share the same position value
    $distinctPositions = array_unique($positions);
    expect(count($distinctPositions))->toBe(2); // 2 agenda items = 2 distinct positions
});
