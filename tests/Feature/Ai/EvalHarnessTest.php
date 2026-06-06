<?php

use App\Ai\Agents\MeetingSummaryAgent;
use App\Ai\Agents\SummaryEvaluationAgent;
use App\Enums\EvaluationStatus;
use App\Models\EvaluationCase;
use App\Models\EvaluationRun;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('failed judge with unmet expected facts results in Failed run and exit 1', function (): void {
    MeetingSummaryAgent::fake([[
        'title' => 'Samenvatting',
        'body' => 'Algemene tekst zonder het verwachte feit.',
        'impact_note' => '',
        'confidence' => 40,
        'flags' => [],
    ]]);

    SummaryEvaluationAgent::fake([[
        'score' => 20,
        'passed' => false,
        'missing_facts' => ['het specifieke feit'],
        'unsupported_claims' => [],
        'reading_level_ok' => true,
        'feedback' => 'De samenvatting mist het kernpunt.',
    ]]);

    $municipality = Municipality::factory()->create();
    EvaluationCase::factory()->create([
        'municipality_id' => $municipality->id,
        'expected_facts' => ['het specifieke feit dat ontbreekt'],
        'forbidden_claims' => null,
        'active' => true,
    ]);

    $this->artisan('volgjeraad:evaluate', ['--live' => true])
        ->assertExitCode(Command::FAILURE);

    expect(EvaluationRun::count())->toBe(1);
    expect(EvaluationRun::first()->status)->toBe(EvaluationStatus::Failed);
});

test('passed judge with no expected facts results in Passed run and exit 0', function (): void {
    MeetingSummaryAgent::fake([[
        'title' => 'Bestemmingsplan goedgekeurd',
        'body' => 'De raad heeft het bestemmingsplan unaniem goedgekeurd.',
        'impact_note' => 'Bewoners kunnen bezwaar indienen.',
        'confidence' => 90,
        'flags' => [],
    ]]);

    SummaryEvaluationAgent::fake([[
        'score' => 88,
        'passed' => true,
        'missing_facts' => [],
        'unsupported_claims' => [],
        'reading_level_ok' => true,
        'feedback' => 'Goed samengevat.',
    ]]);

    $municipality = Municipality::factory()->create();
    EvaluationCase::factory()->create([
        'municipality_id' => $municipality->id,
        'expected_facts' => [],
        'forbidden_claims' => null,
        'active' => true,
    ]);

    $this->artisan('volgjeraad:evaluate', ['--live' => true])
        ->assertExitCode(Command::SUCCESS);

    expect(EvaluationRun::count())->toBe(1);
    expect(EvaluationRun::first()->status)->toBe(EvaluationStatus::Passed);
});
