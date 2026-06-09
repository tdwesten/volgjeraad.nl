<?php

namespace App\Ai\Agents;

use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class NotuleDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        return PromptRepository::load('notule_detection', $this->promptVersion);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'is_notule_present' => $schema->boolean()->required(),
            'media_object_id' => $schema->integer()->nullable(),
            'confidence' => $schema->integer()->required(),
        ];
    }
}
