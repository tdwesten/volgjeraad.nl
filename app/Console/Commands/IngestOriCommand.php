<?php

namespace App\Console\Commands;

use App\Actions\Ingest\IngestMeetings;
use App\Models\Municipality;
use Illuminate\Console\Command;

class IngestOriCommand extends Command
{
    protected $signature = 'volgjeraad:ingest
                            {municipality=brummen : Municipality slug}
                            {--from= : Start date (Y-m-d)}
                            {--to= : End date (Y-m-d)}
                            {--sync : Run synchronously without queue}';

    protected $description = 'Ingest meetings from ORI for a municipality';

    public function handle(IngestMeetings $action): int
    {
        $slug = $this->argument('municipality');
        $municipality = Municipality::where('slug', $slug)->firstOrFail();

        $this->info("Ingesting meetings for {$municipality->name}...");

        $action->handle($municipality);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
