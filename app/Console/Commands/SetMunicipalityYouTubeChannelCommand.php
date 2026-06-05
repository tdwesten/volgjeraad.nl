<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('volgjeraad:set-youtube-channel {municipality : Slug of the municipality} {channel_id : YouTube channel ID (UCxxxxxxxxxx)}')]
#[Description('Set or clear the YouTube channel ID for a municipality')]
class SetMunicipalityYouTubeChannelCommand extends Command
{
    public function handle(): int
    {
        $slug = $this->argument('municipality');
        $channelId = $this->argument('channel_id');

        $municipality = Municipality::where('slug', $slug)->first();

        if ($municipality === null) {
            $this->error("Municipality '{$slug}' not found.");

            return self::FAILURE;
        }

        $settings = $municipality->settings ?? [];
        $settings['youtube_channel_id'] = $channelId;

        $municipality->update(['settings' => $settings]);

        $this->info("YouTube channel ID for {$municipality->name} set to {$channelId}.");

        return self::SUCCESS;
    }
}
