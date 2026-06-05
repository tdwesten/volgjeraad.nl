<?php

use App\Actions\Summaries\GenerateAgendaItemSummary;
use App\Ai\Agents\AgendaSummaryAgent;
use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Models\AgendaItem;
use App\Models\AiUsageRecord;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeItemWithText(string $text = 'Bespreking over het wijzigingsplan voor perceel X.'): AgendaItem
{
    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id]);
    MediaObject::factory()->withText()->create([
        'agenda_item_id' => $item->id,
        'md_text' => $text,
        'has_text' => true,
    ]);

    return $item->fresh();
}

test('generates draft summary with correct level, model, tokens, and AI usage record', function (): void {
    AgendaSummaryAgent::fake([[
        'title' => 'Wijzigingsplan Perceel X',
        'body' => 'De raad bespreekt het wijzigingsplan.',
        'impact_note' => 'Kan invloed hebben op omwonenden.',
        'confidence' => 85,
        'flags' => [],
    ]]);

    $item = makeItemWithText();
    $action = app(GenerateAgendaItemSummary::class);
    $summary = $action->handle($item, SummaryLevel::Standard);

    expect($summary)->not->toBeNull();
    expect($summary->status)->toBe(SummaryStatus::Draft);
    expect($summary->level)->toBe(SummaryLevel::Standard);
    expect($summary->title)->toBe('Wijzigingsplan Perceel X');
    expect($summary->confidence)->toBe(85);

    expect(AiUsageRecord::count())->toBe(1);
    expect(AiUsageRecord::first()->status)->toBe('ok');
});

test('cost cap reached returns null and records capped usage', function (): void {
    AgendaSummaryAgent::fake([]);

    $item = makeItemWithText();
    $meeting = $item->meeting;
    $model = config('volgjeraad.ai.default_summary_model');

    // Pre-fill cost to exceed cap
    AiUsageRecord::create([
        'municipality_id' => $meeting->municipality_id,
        'meeting_id' => $meeting->id,
        'subject_type' => $item->getMorphClass(),
        'subject_id' => $item->id,
        'provider' => 'openai',
        'model' => $model,
        'prompt_version' => 'v1',
        'operation' => 'agenda_summary',
        'input_tokens' => 1_000_000,
        'output_tokens' => 1_000_000,
        'cost_cents' => 10_000,
        'status' => 'ok',
    ]);

    $action = app(GenerateAgendaItemSummary::class);
    $result = $action->handle($item, SummaryLevel::Standard);

    expect($result)->toBeNull();
    expect(Summary::count())->toBe(0);
    expect(AiUsageRecord::where('status', 'capped')->count())->toBe(1);
});

test('empty source text creates draft with source_text_missing flag and no AI call', function (): void {
    AgendaSummaryAgent::fake([]);

    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    $item = AgendaItem::factory()->create(['meeting_id' => $meeting->id]);
    // No media objects with text

    $action = app(GenerateAgendaItemSummary::class);
    $summary = $action->handle($item, SummaryLevel::Standard);

    expect($summary)->not->toBeNull();
    expect($summary->flags)->toContain('source_text_missing');
    expect($summary->confidence)->toBe(0);
    expect(AiUsageRecord::count())->toBe(0);
});
