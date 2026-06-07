<?php

namespace App\Actions\Ingest;

use App\Actions\Logging\RecordProcessingEvent;
use App\Enums\IngestMode;
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
        private RecordProcessingEvent $log,
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

        // Pass 1: upsert all meetings with MetadataOnly; track which changed
        $ingestedOriIds = [];
        $changedOriIds = [];

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

            $ingestedOriIds[] = $oriId;

            $existing = Meeting::where('municipality_id', $m->id)
                ->where('ori_id', $oriId)
                ->first();

            if ($existing && $existing->raw_payload_hash === $hash) {
                $existing->update(['last_seen_at' => now()]);

                continue;
            }

            $changedOriIds[] = $oriId;

            Meeting::updateOrCreate(
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
                    'ingest_mode' => IngestMode::MetadataOnly->value,
                    'last_seen_at' => now(),
                ],
            );
        }

        // Pass 2: determine summarizable council meetings with full DB context
        $launchDate = $m->launch_date ? CarbonImmutable::instance($m->launch_date) : null;

        if ($launchDate === null || empty($ingestedOriIds)) {
            return;
        }

        $summarizeTypes = $m->summarizeTypes();
        $summarizableOriIds = [];

        // Summarizable meetings on or after launch date (within ingested set)
        $afterLaunchIds = Meeting::where('municipality_id', $m->id)
            ->whereIn('type', $summarizeTypes)
            ->whereIn('ori_id', $ingestedOriIds)
            ->where('starts_at', '>=', $launchDate)
            ->pluck('ori_id')
            ->all();

        $summarizableOriIds = array_merge($summarizableOriIds, $afterLaunchIds);

        // Top-N most recent summarizable meetings before launch date (global backfill)
        $backfillCount = (int) $m->backfill_recent_meetings;
        if ($backfillCount > 0) {
            $backfillIds = Meeting::where('municipality_id', $m->id)
                ->whereIn('type', $summarizeTypes)
                ->where('starts_at', '<', $launchDate)
                ->orderByDesc('starts_at')
                ->limit($backfillCount)
                ->pluck('ori_id')
                ->all();

            $summarizableOriIds = array_merge($summarizableOriIds, $backfillIds);
        }

        $summarizableOriIds = array_unique($summarizableOriIds);

        if (empty($summarizableOriIds)) {
            return;
        }

        // Update ingest_mode to Summarize for the entire summarizable set
        Meeting::where('municipality_id', $m->id)
            ->whereIn('ori_id', $summarizableOriIds)
            ->update(['ingest_mode' => IngestMode::Summarize->value]);

        // Dispatch agenda job for meetings that still need agenda ingest.
        // A hash-skip in pass 1 must not block this when agenda_ingested_at is null.
        $toDispatch = Meeting::where('municipality_id', $m->id)
            ->whereIn('ori_id', $summarizableOriIds)
            ->where(function ($query) use ($changedOriIds): void {
                $query->whereNull('agenda_ingested_at')
                    ->orWhereIn('ori_id', $changedOriIds);
            })
            ->get();

        $this->log->handle(
            null,
            'ingest',
            'info',
            count($ingestedOriIds).' vergaderingen gevonden, '.count($changedOriIds).' gewijzigd, '.count($toDispatch).' agenda-ingest gedispatcht',
            ['municipality' => $m->name],
            $m->id,
        );

        foreach ($toDispatch as $meeting) {
            dispatch(new IngestMeetingAgendaJob($meeting->id));
        }
    }
}
