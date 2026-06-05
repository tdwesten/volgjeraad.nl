<?php

namespace App\Ai\Agents;

use App\Models\EvaluationCase;
use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class SummaryEvaluationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public EvaluationCase $case,
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        return PromptRepository::load('summary_evaluation', $this->promptVersion);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'score' => $schema->integer()->required(),
            'passed' => $schema->boolean()->required(),
            'missing_facts' => $schema->array()->items($schema->string())->required(),
            'unsupported_claims' => $schema->array()->items($schema->string())->required(),
            'reading_level_ok' => $schema->boolean()->required(),
            'feedback' => $schema->string()->required(),
        ];
    }
}
