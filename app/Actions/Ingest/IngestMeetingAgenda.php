<?php

namespace App\Actions\Ingest;

use App\Jobs\IngestAgendaMediaObjectsJob;
use App\Models\AgendaItem;
use App\Models\Meeting;
use App\Services\Ori\OriClient;
use App\Services\Ori\OriNormalizer;
use App\Support\PayloadHasher;

class IngestMeetingAgenda
{
    public function __construct(private OriClient $client) {}

    public function handle(Meeting $meeting): void
    {
        $source = $meeting->raw_payload ?? [];
        $agendaIds = OriNormalizer::ids($source['agenda'] ?? null);

        if (empty($agendaIds)) {
            $meeting->update(['agenda_ingested_at' => now()]);

            return;
        }

        $sources = $this->client->fetchByIds($meeting->municipality, $agendaIds);

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

            if ($changed) {
                $item = AgendaItem::where('meeting_id', $meeting->id)
                    ->where('ori_id', $oriId)
                    ->first();

                dispatch(new IngestAgendaMediaObjectsJob($item->id));
            }
        }

        $meeting->update(['agenda_ingested_at' => now()]);
    }
}
