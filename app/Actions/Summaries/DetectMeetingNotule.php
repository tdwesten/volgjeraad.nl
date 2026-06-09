<?php

namespace App\Actions\Summaries;

use App\Actions\Logging\RecordProcessingEvent;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Models\Meeting;
use App\Support\PromptRepository;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

class DetectMeetingNotule
{
    public function __construct(private RecordProcessingEvent $log) {}

    public function handle(Meeting $meeting): void
    {
        if ($meeting->notule_detected_at !== null) {
            return;
        }

        $docs = [];
        foreach ($meeting->agendaItems()->with('mediaObjects')->get() as $item) {
            foreach ($item->mediaObjects as $media) {
                $docs[] = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                ];
            }
        }

        if ($docs === []) {
            return;
        }

        $model = (string) config('volgjeraad.ai.default_summary_model');
        $threshold = (int) config('volgjeraad.ai.notule_confidence_threshold');
        $agent = new NotuleDetectionAgent($model, PromptRepository::version());

        try {
            $response = $agent->prompt(
                json_encode(['documents' => $docs], JSON_UNESCAPED_UNICODE),
                provider: Lab::OpenAI,
                model: $model,
            );
        } catch (Throwable $e) {
            Log::warning('detect_meeting_notule failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $structured = $response->structured ?? [];
        $present = (bool) ($structured['is_notule_present'] ?? false);
        $confidence = (int) ($structured['confidence'] ?? 0);

        if (! $present || $confidence < $threshold) {
            return;
        }

        $meeting->update([
            'notule_detected_at' => now(),
            'notule_media_object_id' => $structured['media_object_id'] ?? null,
        ]);

        $this->log->handle($meeting, 'notule', 'success', "Notule gevonden (confidence: {$confidence}%)");
    }
}
