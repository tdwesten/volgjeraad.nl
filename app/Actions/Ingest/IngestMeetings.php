<?php

namespace App\Actions\Ingest;

use App\Enums\MeetingType;
use App\Jobs\IngestMeetingAgendaJob;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Services\Ori\OriClient;
use App\Services\Ori\OriNormalizer;
use App\Support\PayloadHasher;
use Carbon\CarbonImmutable;

class IngestMeetings
{
    public function __construct(
        private OriClient $client,
        private DetermineIngestMode $determineIngestMode,
    ) {}

    public function handle(Municipality $m): void
    {
        $from = CarbonImmutable::now()->subDays((int) config('volgjeraad.ori.window_past_days'));
        $to = CarbonImmutable::now()->addDays((int) config('volgjeraad.ori.window_future_days'));

        $hits = $this->client->searchMeetings($m, $from, $to);

        // Collect unique committee IDs to resolve organization names
        $committeeIds = [];
        foreach ($hits as $hit) {
            $source = $hit['_source'] ?? [];
            $ids = OriNormalizer::ids($source['committee'] ?? null);
            foreach ($ids as $cid) {
                $committeeIds[$cid] = true;
            }
        }

        $orgNames = [];
        if ($committeeIds) {
            $orgSources = $this->client->fetchByIds($m, array_keys($committeeIds));
            foreach ($orgSources as $id => $src) {
                $orgNames[$id] = $src['name'] ?? null;
            }
        }

        foreach ($hits as $hit) {
            $oriId = $hit['_id'];
            $source = $hit['_source'] ?? [];

            $committeeOriId = OriNormalizer::ids($source['committee'] ?? null)[0] ?? null;
            $committeeName = $committeeOriId ? ($orgNames[$committeeOriId] ?? null) : null;

            if ($committeeName === null) {
                $committeeName = OriNormalizer::organizationName($source);
            }

            $type = MeetingType::fromCommitteeName($committeeName, $m->raad_pattern);
            $normalized = OriNormalizer::meeting($oriId, $source);
            $hash = PayloadHasher::hash($source);
            $startsAt = isset($source['start_date'])
                ? CarbonImmutable::parse($source['start_date'])
                : null;

            $ingestMode = $this->determineIngestMode->handle($m, $startsAt, $type);

            $existing = Meeting::where('municipality_id', $m->id)
                ->where('ori_id', $oriId)
                ->first();

            if ($existing && $existing->raw_payload_hash === $hash) {
                $existing->update(['last_seen_at' => now()]);

                continue;
            }

            $meeting = Meeting::updateOrCreate(
                ['municipality_id' => $m->id, 'ori_id' => $oriId],
                [
                    'type' => $type->value,
                    'committee_ori_id' => $committeeOriId,
                    'committee_name' => $committeeName,
                    'name' => $normalized['name'],
                    'starts_at' => $startsAt,
                    'status' => $normalized['status'],
                    'source_url' => $normalized['source_url'],
                    'raw_payload' => $source,
                    'raw_payload_hash' => $hash,
                    'ingest_mode' => $ingestMode->value,
                    'last_seen_at' => now(),
                ],
            );

            if ($meeting->shouldSummarize()) {
                dispatch(new IngestMeetingAgendaJob($meeting->id));
            }
        }
    }
}
