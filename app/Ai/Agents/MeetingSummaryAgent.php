<?php

namespace App\Ai\Agents;

use App\Enums\SummaryLevel;
use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class MeetingSummaryAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public SummaryLevel $level,
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        $key = "meeting_summary.{$this->level->value}";

        return PromptRepository::load($key, $this->promptVersion);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'body' => $schema->string()->required(),
            'impact_note' => $schema->string()->required(),
            'confidence' => $schema->integer()->required(),
            'flags' => $schema->array()->items(
                $schema->string()->enum(['source_text_missing', 'low_confidence', 'contains_uncertainty', 'needs_human_attention'])
            )->required(),
        ];
    }
}
