<?php

namespace App\Ai\Agents;

use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class VideoMatchAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        return PromptRepository::load('video_match', $this->promptVersion);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'video_id' => $schema->string()->required(),
            'confidence' => $schema->integer()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
