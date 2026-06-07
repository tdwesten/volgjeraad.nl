<?php

namespace App\Ai\Agents;

use App\Ai\Tools\YouTubeSearchTool;
use App\Services\YouTube\YouTubeClient;
use App\Support\PromptRepository;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

#[MaxSteps(6)]
class StreamFinderAgent implements Agent, HasStructuredOutput, HasTools
{
    use Promptable;

    public function __construct(
        public string $model,
        public string $promptVersion,
    ) {}

    public function instructions(): string
    {
        return PromptRepository::load('stream_finder', $this->promptVersion);
    }

    /**
     * @return iterable<int, Tool>
     */
    public function tools(): iterable
    {
        return [
            new YouTubeSearchTool(app(YouTubeClient::class)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'channel_id' => $schema->string(),
            'channel_title' => $schema->string(),
            'channel_url' => $schema->string(),
            'confidence' => $schema->integer()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
