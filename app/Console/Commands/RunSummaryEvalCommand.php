<?php

namespace App\Console\Commands;

use App\Actions\Ai\EvaluateSummaryCase;
use App\Ai\Agents\AgendaSummaryAgent;
use App\Ai\Agents\SummaryEvaluationAgent;
use App\Enums\EvaluationStatus;
use App\Models\EvaluationCase;
use App\Support\PromptRepository;
use Illuminate\Console\Command;

class RunSummaryEvalCommand extends Command
{
    protected $signature = 'volgjeraad:evaluate
                            {--prompt-version= : Prompt version (default: from config)}
                            {--model= : Model to use (default: from config)}
                            {--live : Use real API key instead of fakes}';

    protected $description = 'Run active evaluation cases and report pass/fail results';

    public function handle(EvaluateSummaryCase $action): int
    {
        $promptVersion = $this->option('prompt-version') ?? PromptRepository::version();
        $model = $this->option('model') ?? (string) config('volgjeraad.ai.default_eval_model');
        $live = (bool) $this->option('live');

        if (! $live) {
            AgendaSummaryAgent::fake([null]);
            SummaryEvaluationAgent::fake([[
                'score' => 75,
                'passed' => true,
                'missing_facts' => [],
                'unsupported_claims' => [],
                'reading_level_ok' => true,
                'feedback' => 'Fake evaluation — use --live for real results.',
            ]]);
        }

        $cases = EvaluationCase::where('active', true)->get();

        if ($cases->isEmpty()) {
            $this->warn('No active evaluation cases found.');

            return self::SUCCESS;
        }

        $rows = [];
        $anyFailed = false;

        foreach ($cases as $case) {
            $run = $action->handle($case, $promptVersion, $model);

            $rows[] = [
                $case->name,
                $run->status->value,
                $run->score,
                $run->status === EvaluationStatus::Failed ? '✗' : '✓',
            ];

            if ($run->status === EvaluationStatus::Failed) {
                $anyFailed = true;
            }
        }

        $this->table(['Case', 'Status', 'Score', ''], $rows);

        if ($anyFailed) {
            $this->error('One or more evaluation cases failed.');

            return self::FAILURE;
        }

        $this->info('All evaluation cases passed.');

        return self::SUCCESS;
    }
}
