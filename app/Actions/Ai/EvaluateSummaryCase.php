<?php

namespace App\Actions\Ai;

use App\Ai\Agents\MeetingSummaryAgent;
use App\Ai\Agents\SummaryEvaluationAgent;
use App\Enums\EvaluationStatus;
use App\Enums\SummaryLevel;
use App\Models\EvaluationCase;
use App\Models\EvaluationRun;
use Laravel\Ai\Enums\Lab;

class EvaluateSummaryCase
{
    public function handle(EvaluationCase $case, string $promptVersion, string $model): EvaluationRun
    {
        // Generate summary from source_text using the appropriate level agent
        $level = SummaryLevel::from($case->level);
        $summaryAgent = new MeetingSummaryAgent($level, $model, $promptVersion);
        $summaryResponse = $summaryAgent->prompt($case->source_text, provider: Lab::OpenAI, model: $model);
        $summaryText = $summaryResponse->text ?? '';

        // Run LLM judge
        $evalAgent = new SummaryEvaluationAgent($case, $model, $promptVersion);
        $evalResponse = $evalAgent->prompt(
            "Source text:\n{$case->source_text}\n\nGenerated summary:\n{$summaryText}",
            provider: Lab::OpenAI,
            model: $model,
        );

        $evalData = $evalResponse->structured ?? [];

        // Deterministic checklist
        $checklistResults = [];
        $allFactsPresent = true;
        $noForbiddenClaims = true;

        foreach ($case->expected_facts ?? [] as $fact) {
            $found = str_contains(mb_strtolower($summaryText), mb_strtolower($fact));
            $checklistResults[] = ['fact' => $fact, 'type' => 'expected', 'found' => $found];
            if (! $found) {
                $allFactsPresent = false;
            }
        }

        foreach ($case->forbidden_claims ?? [] as $claim) {
            $present = str_contains(mb_strtolower($summaryText), mb_strtolower($claim));
            $checklistResults[] = ['claim' => $claim, 'type' => 'forbidden', 'absent' => ! $present];
            if ($present) {
                $noForbiddenClaims = false;
            }
        }

        // Determine status
        $judgePassedBool = (bool) ($evalData['passed'] ?? false);
        $deterministicPass = $allFactsPresent && $noForbiddenClaims;

        $status = match (true) {
            $judgePassedBool && $deterministicPass => EvaluationStatus::Passed,
            ! $judgePassedBool && ! $deterministicPass => EvaluationStatus::Failed,
            default => EvaluationStatus::NeedsReview,
        };

        return EvaluationRun::create([
            'evaluation_case_id' => $case->id,
            'prompt_version' => $promptVersion,
            'model' => $model,
            'status' => $status->value,
            'score' => (int) ($evalData['score'] ?? 0),
            'checklist_results' => $checklistResults,
            'judge_feedback' => $evalData['feedback'] ?? null,
        ]);
    }
}
