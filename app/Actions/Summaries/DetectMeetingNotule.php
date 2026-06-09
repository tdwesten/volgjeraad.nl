<?php

namespace App\Actions\Summaries;

use App\Actions\Logging\RecordProcessingEvent;
use App\Ai\Agents\NotuleDetectionAgent;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Support\PromptRepository;
use Illuminate\Http\Client\StrayRequestException;
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
        $validIds = [];
        foreach ($meeting->agendaItems()->with('mediaObjects')->get() as $item) {
            foreach ($item->mediaObjects as $media) {
                $docs[] = self::documentPayload($media);
                $validIds[] = (int) $media->id;
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
        } catch (StrayRequestException $e) {
            // Test-only: deze exceptie bestaat alléén onder Http::preventStrayRequests()
            // (de hermetische testsuite). In productie komt 'ie nooit voor, dus dit is
            // daar een no-op. We zwelgen 'm bewust NIET zodat een test die dit pad
            // ongefaket raakt hard faalt i.p.v. stilletjes 'geen notule' te concluderen.
            throw $e;
        } catch (Throwable $e) {
            Log::warning('detect_meeting_notule failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);
            // Poging gedaan (ook al faalde de AI) → markeer zodat de sweep niet
            // elke 15 min opnieuw probeert.
            $meeting->update(['notule_checked_at' => now()]);

            return;
        }

        // Poging gedaan → throttle-stempel zetten ongeacht de uitkomst.
        $meeting->update(['notule_checked_at' => now()]);

        $structured = $response->structured ?? [];
        $present = (bool) ($structured['is_notule_present'] ?? false);
        $confidence = (int) ($structured['confidence'] ?? 0);

        if (! $present || $confidence < $threshold) {
            return;
        }

        // Accepteer het door de AI geretourneerde id alleen als het echt bij deze
        // meeting hoort; presence mag waar zijn met een null id.
        $candidate = $structured['media_object_id'] ?? null;
        $mediaObjectId = ($candidate !== null && in_array((int) $candidate, $validIds, true))
            ? (int) $candidate
            : null;

        $meeting->update([
            'notule_detected_at' => now(),
            'notule_media_object_id' => $mediaObjectId,
        ]);

        $this->log->handle($meeting, 'notule', 'success', "Notule gevonden (confidence: {$confidence}%)");
    }

    /**
     * Bouw de documentregel voor de AI-agent. Neemt de document/upload-datum mee
     * indien beschikbaar in het media raw_payload.
     *
     * @return array<string, mixed>
     */
    public static function documentPayload(MediaObject $media): array
    {
        $doc = [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
        ];

        $date = data_get($media->raw_payload, 'date');
        if ($date !== null) {
            $doc['date'] = $date;
        }

        return $doc;
    }
}
