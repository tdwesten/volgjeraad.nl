<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('volgjeraad:set-launch-date {municipality : Slug of the municipality} {date : Launch date (Y-m-d)}')]
#[Description('Set the launch date for a municipality (bepaalt welke vergaderingen samengevat worden)')]
class SetMunicipalityLaunchDateCommand extends Command
{
    public function handle(): int
    {
        $slug = $this->argument('municipality');
        $date = $this->argument('date');

        $municipality = Municipality::where('slug', $slug)->first();

        if ($municipality === null) {
            $this->error("Municipality '{$slug}' not found.");

            return self::FAILURE;
        }

        try {
            $parsed = CarbonImmutable::parse($date)->toDateString();
        } catch (\Throwable) {
            $this->error("Invalid date '{$date}'. Use format Y-m-d.");

            return self::FAILURE;
        }

        $municipality->update(['launch_date' => $parsed]);

        $this->info("Launch date for {$municipality->name} set to {$parsed}.");

        return self::SUCCESS;
    }
}
