<?php

namespace App\Console\Commands;

use App\Actions\Ingest\IngestMeetings;
use App\Enums\IngestMode;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Console\Command;

class BackfillCommand extends Command
{
    protected $signature = 'volgjeraad:backfill
                            {municipality : Municipality slug}';

    protected $description = 'Backfill historical meetings (metadata-only) and set ingest cutoff';

    public function handle(IngestMeetings $action): int
    {
        $slug = $this->argument('municipality');
        $municipality = Municipality::where('slug', $slug)->firstOrFail();

        $this->info("Backfilling meetings for {$municipality->name} (metadata-only)...");

        $action->handle($municipality);

        // Mark all existing council meetings before launch_date as MetadataOnly
        if ($municipality->launch_date) {
            Meeting::where('municipality_id', $municipality->id)
                ->where('starts_at', '<', $municipality->launch_date)
                ->update(['ingest_mode' => IngestMode::MetadataOnly->value]);
        }

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
