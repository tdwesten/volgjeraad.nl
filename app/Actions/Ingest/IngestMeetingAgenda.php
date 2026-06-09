<?php

namespace App\Actions\Ingest;

use App\Actions\Logging\RecordProcessingEvent;
use App\Jobs\IngestAgendaMediaObjectsJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Services\Ori\OriClient;
use App\Services\Ori\OriNormalizer;
use App\Support\PayloadHasher;
use Illuminate\Support\Facades\Log;

class IngestMeetingAgenda
{
    public function __construct(private OriClient $client, private RecordProcessingEvent $log) {}

    /**
     * @param  bool  $forceMedia  Haal de bijlagen van ÁLLE agendapunten opnieuw op,
     *                            niet alleen van gewijzigde — gebruikt bij handmatig
     *                            herverwerken zodat ontbrekende/verdwenen documenten
     *                            betrouwbaar terugkomen (in-place ververst, niet gewist).
     */
    public function handle(Meeting $meeting, bool $forceMedia = false): void
    {
        $source = $meeting->raw_payload ?? [];
        $agendaIds = OriNormalizer::meeting($meeting->ori_id, $source)['agenda_ids'];

        if (empty($agendaIds)) {
            $meeting->update(['agenda_ingested_at' => now()]);

            return;
        }

        $sources = $this->client->fetchByIds($meeting->municipality, $agendaIds);

        // Pass 1: upsert ALL items before dispatching any media jobs.
        // This ensures pendingCount is based on the full set, not a partial set,
        // preventing dispatchSummarizeIfComplete from firing prematurely.
        $changedIds = [];
        $allIds = [];
        foreach ($sources as $oriId => $itemSource) {
            $hash = PayloadHasher::hash($itemSource);
            $normalized = OriNormalizer::agendaItem($oriId, $itemSource);

            $existing = AgendaItem::where('meeting_id', $meeting->id)
                ->where('ori_id', $oriId)
                ->first();

            $changed = ! $existing || $existing->raw_payload_hash !== $hash;

            AgendaItem::updateOrCreate(
                ['meeting_id' => $meeting->id, 'ori_id' => $oriId],
                [
                    'position' => $normalized['position'],
                    'name' => $normalized['name'],
                    'raw_payload' => $itemSource,
                    'raw_payload_hash' => $hash,
                    'last_seen_at' => now(),
                ],
            );

            $item = AgendaItem::where('meeting_id', $meeting->id)
                ->where('ori_id', $oriId)
                ->first();
            $allIds[$oriId] = $item->id;

            if ($changed) {
                $changedIds[$oriId] = $item->id;
            }
        }

        // Pass 2: dispatch media object jobs now that all items are in the DB.
        // Bij forceMedia halen we de bijlagen van álle items opnieuw op.
        $dispatchIds = $forceMedia ? $allIds : $changedIds;
        foreach ($dispatchIds as $oriId => $itemId) {
            try {
                dispatch(new IngestAgendaMediaObjectsJob($itemId));
            } catch (\Throwable $e) {
                Log::warning('IngestAgendaMediaObjectsJob failed', [
                    'agenda_item_id' => $itemId,
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $meeting->update(['agenda_ingested_at' => now()]);

        $this->log->handle(
            $meeting,
            'agenda',
            'success',
            count($sources).' agendapunten opgehaald, '.count($changedIds).' gewijzigd',
        );
    }
}
