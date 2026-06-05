<?php

namespace App\Actions\Ingest;

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\Municipality;
use Carbon\CarbonImmutable;

class DetermineIngestMode
{
    public function handle(Municipality $m, ?CarbonImmutable $startsAt, MeetingType $type): IngestMode
    {
        if ($type !== MeetingType::Council) {
            return IngestMode::MetadataOnly;
        }

        $launchDate = $m->launch_date ? CarbonImmutable::instance($m->launch_date) : null;

        if ($launchDate === null) {
            return IngestMode::MetadataOnly;
        }

        if ($startsAt !== null && $startsAt->gte($launchDate)) {
            return IngestMode::Summarize;
        }

        // Within the last N council meetings before launch date
        $backfillCount = (int) $m->backfill_recent_meetings;
        if ($backfillCount > 0 && $startsAt !== null) {
            $recentCouncilIds = Meeting::where('municipality_id', $m->id)
                ->where('type', MeetingType::Council->value)
                ->where('starts_at', '<', $launchDate)
                ->orderByDesc('starts_at')
                ->limit($backfillCount)
                ->pluck('starts_at', 'ori_id');

            $matchedByTime = $recentCouncilIds->contains(fn ($s) => CarbonImmutable::parse($s)->eq($startsAt));
            if ($matchedByTime) {
                return IngestMode::Summarize;
            }
        }

        return IngestMode::MetadataOnly;
    }
}
