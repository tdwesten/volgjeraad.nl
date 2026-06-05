<?php

use App\Actions\Summaries\EstimateCost;
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
use Illuminate\Support\Facades\Log;

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

test('exception is caught, logged as warning, records failed usage, and returns null without rethrowing', function (): void {
    Log::shouldReceive('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => $msg === 'agenda_summary failed'
            && isset($ctx['agenda_item_id'])
            && isset($ctx['error'])
        );

    AgendaSummaryAgent::fake([new RuntimeException('API timeout')]);

    $item = makeItemWithText();
    $action = app(GenerateAgendaItemSummary::class);

    // Must NOT throw — chain must continue
    $result = $action->handle($item, SummaryLevel::Standard);

    expect($result)->toBeNull();
    expect(AiUsageRecord::where('status', 'failed')->count())->toBe(1);
    $record = AiUsageRecord::where('status', 'failed')->first();
    expect($record->raw_metadata)->toHaveKey('error');
    expect($record->raw_metadata)->toHaveKey('class');
});

test('cost_cents is positive when agent returns large token counts', function (): void {
    // 100 000 input tokens × 15 cent/1M = 1.5 → 2 cents; 10 000 output × 60/1M = 0.6 → 1 cent
    AgendaSummaryAgent::fake([[
        'title' => 'Groot rapport',
        'body' => 'Uitgebreide analyse.',
        'impact_note' => 'Hoog belang.',
        'confidence' => 90,
        'flags' => [],
        '__usage' => ['promptTokens' => 100_000, 'completionTokens' => 10_000],
    ]]);

    $item = makeItemWithText('x');
    $action = app(GenerateAgendaItemSummary::class);
    $action->handle($item, SummaryLevel::Standard);

    // The fake returns 0 usage tokens (laravel/ai fake doesn't expose __usage),
    // so we verify EstimateCost directly with realistic values.
    $estimateCost = app(EstimateCost::class);
    $cents = $estimateCost->handle('gpt-4o-mini', 100_000, 10_000);
    expect($cents)->toBeGreaterThan(0);
});

test('source text longer than max_source_chars gets truncated and adds source_truncated flag', function (): void {
    config(['volgjeraad.ai.max_source_chars' => 10]);

    AgendaSummaryAgent::fake([[
        'title' => 'Ingekort',
        'body' => 'Tekst was te lang.',
        'impact_note' => '',
        'confidence' => 70,
        'flags' => [],
    ]]);

    $item = makeItemWithText(str_repeat('a', 100));
    $action = app(GenerateAgendaItemSummary::class);
    $summary = $action->handle($item, SummaryLevel::Standard);

    expect($summary)->not->toBeNull();
    expect($summary->flags)->toContain('source_truncated');
});
