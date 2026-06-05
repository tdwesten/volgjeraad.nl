<?php

namespace App\Actions\Summaries;

use App\Ai\Agents\AgendaSummaryAgent;
use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Models\AgendaItem;
use App\Models\Summary;
use App\Support\PayloadHasher;
use App\Support\PromptRepository;
use Laravel\Ai\Enums\Lab;
use Throwable;

class GenerateAgendaItemSummary
{
    public function __construct(
        private CheckMeetingCost $checkMeetingCost,
        private EstimateCost $estimateCost,
        private RecordAiUsage $recordAiUsage,
    ) {}

    public function handle(AgendaItem $item, SummaryLevel $level): ?Summary
    {
        $meeting = $item->meeting;
        $municipality = $meeting->municipality;
        $model = (string) config('volgjeraad.ai.default_summary_model');
        $promptVersion = PromptRepository::version();
        $costCapCents = (int) config('volgjeraad.ai.cost_cap_cents_per_meeting');

        $sourceText = $item->sourceText();

        // Empty source text — draft with flag, no AI call
        if ($sourceText === '') {
            return Summary::create([
                'summarizable_type' => $item->getMorphClass(),
                'summarizable_id' => $item->getKey(),
                'municipality_id' => $municipality->id,
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'language' => 'nl',
                'source_hash' => PayloadHasher::hash(['text' => '']),
                'status' => SummaryStatus::Draft->value,
                'title' => '',
                'body' => '',
                'impact_note' => null,
                'confidence' => 0,
                'flags' => ['source_text_missing'],
                'input_tokens' => 0,
                'output_tokens' => 0,
                'prompt_version' => $promptVersion,
                'model' => $model,
            ]);
        }

        // Cost-cap check
        $currentCost = $this->checkMeetingCost->handle($meeting, $model);
        if ($currentCost >= $costCapCents) {
            $this->recordAiUsage->handle(
                $item, $municipality, $meeting,
                'agenda_summary', 'openai', $model, $promptVersion,
                0, 0, 0, 'capped',
            );

            return null;
        }

        $agent = new AgendaSummaryAgent($level, $model, $promptVersion);

        try {
            $response = $agent->prompt($sourceText, provider: Lab::OpenAI, model: $model);

            $structured = $response->structured ?? [];
            $inputTokens = $response->usage->promptTokens;
            $outputTokens = $response->usage->completionTokens;
            $costCents = $this->estimateCost->handle($model, $inputTokens, $outputTokens);

            $summary = Summary::create([
                'summarizable_type' => $item->getMorphClass(),
                'summarizable_id' => $item->getKey(),
                'municipality_id' => $municipality->id,
                'meeting_id' => $meeting->id,
                'level' => $level->value,
                'language' => 'nl',
                'source_hash' => PayloadHasher::hash(['text' => $sourceText]),
                'status' => SummaryStatus::Draft->value,
                'title' => $structured['title'] ?? null,
                'body' => $structured['body'] ?? null,
                'impact_note' => $structured['impact_note'] ?? null,
                'confidence' => $structured['confidence'] ?? 0,
                'flags' => $structured['flags'] ?? [],
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'prompt_version' => $promptVersion,
                'model' => $model,
            ]);

            $this->recordAiUsage->handle(
                $item, $municipality, $meeting,
                'agenda_summary', 'openai', $model, $promptVersion,
                $inputTokens, $outputTokens, $costCents, 'ok',
            );

            return $summary;
        } catch (Throwable $e) {
            $this->recordAiUsage->handle(
                $item, $municipality, $meeting,
                'agenda_summary', 'openai', $model, $promptVersion,
                0, 0, 0, 'failed',
            );

            throw $e;
        }
    }
}
