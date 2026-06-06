<?php

namespace App\Actions\Ingest;

use App\Actions\Logging\RecordProcessingEvent;
use App\Actions\Summaries\DispatchMeetingSummariesIfReady;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Services\Ori\OriClient;
use App\Services\Ori\OriNormalizer;
use App\Support\PayloadHasher;

class IngestAgendaMediaObjects
{
    public function __construct(
        private OriClient $client,
        private DispatchMeetingSummariesIfReady $dispatchMeetingSummaries,
        private RecordProcessingEvent $log,
    ) {}

    public function handle(AgendaItem $item): void
    {
        $source = $item->raw_payload ?? [];
        $attachmentIds = OriNormalizer::ids($source['attachment'] ?? null);

        if (! empty($attachmentIds)) {
            $sources = $this->client->fetchByIds($item->meeting->municipality, $attachmentIds);

            foreach ($sources as $oriId => $objSource) {
                $hash = PayloadHasher::hash($objSource);
                $normalized = OriNormalizer::mediaObject($oriId, $objSource);

                MediaObject::updateOrCreate(
                    ['agenda_item_id' => $item->id, 'ori_id' => $oriId],
                    [
                        'position' => $normalized['position'],
                        'name' => $normalized['name'],
                        'file_name' => $normalized['file_name'],
                        'content_type' => $normalized['content_type'],
                        'size_in_bytes' => $normalized['size_in_bytes'],
                        'url' => $normalized['url'],
                        'original_url' => $normalized['original_url'],
                        'text' => $normalized['text'],
                        'md_text' => $normalized['md_text'],
                        'text_pages' => null,
                        'has_text' => $normalized['has_text'],
                        'text_missing_reason' => $normalized['text_missing_reason'],
                        'raw_payload_hash' => $hash,
                    ],
                );
            }
        }

        $item->update(['attachments_fetched_at' => now()]);

        $this->dispatchSummarizeIfComplete($item);
    }

    private function dispatchSummarizeIfComplete(AgendaItem $item): void
    {
        $meeting = $item->meeting;

        $pendingCount = $meeting->agendaItems()
            ->whereNull('attachments_fetched_at')
            ->count();

        if ($pendingCount > 0) {
            return;
        }

        $this->log->handle($meeting, 'media', 'success', 'Alle bijlagen opgehaald, samenvattingspijplijn gestart');

        $this->dispatchMeetingSummaries->handle($meeting);
    }
}
