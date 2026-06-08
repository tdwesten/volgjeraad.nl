<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VolgjeraadStatusCommand extends Command
{
    protected $signature = 'volgjeraad:status {municipality? : Gemeente-slug (leeg = alle gemeenten)}';

    protected $description = 'Toon ingest-, queue- en samenvatting-status (tinker-vrije diagnose)';

    public function handle(): int
    {
        $this->components->info('Queue');
        $this->table(
            ['Wachtend', 'Mislukt'],
            [[DB::table('jobs')->count(), DB::table('failed_jobs')->count()]],
        );

        $slug = $this->argument('municipality');
        $municipalities = $slug
            ? Municipality::where('slug', $slug)->get()
            : Municipality::query()->orderBy('name')->get();

        if ($municipalities->isEmpty()) {
            $this->error("Geen gemeente gevonden voor: {$slug}");

            return self::FAILURE;
        }

        foreach ($municipalities as $municipality) {
            $this->components->info($municipality->name);

            $launch = $municipality->launch_date?->toDateString() ?? 'NIET GEZET';
            $this->line("  launch_date: {$launch}  |  actief: ".($municipality->active ? 'ja' : 'nee'));

            $ingestRows = Meeting::where('municipality_id', $municipality->id)
                ->selectRaw('ingest_mode, count(*) as aantal')
                ->groupBy('ingest_mode')
                ->pluck('aantal', 'ingest_mode');

            $this->table(
                ['Ingest-mode', 'Aantal'],
                $ingestRows->map(fn (int $n, string $mode): array => [$mode, $n])->values()->all(),
            );

            $statuses = Meeting::where('municipality_id', $municipality->id)
                ->with('summaries')
                ->get()
                ->groupBy(fn (Meeting $m): string => $m->summaryStatusLabel())
                ->map->count();

            $this->table(
                ['Samenvatting-status', 'Aantal'],
                $statuses->map(fn (int $n, string $label): array => [$label, $n])->values()->all(),
            );
        }

        return self::SUCCESS;
    }
}
