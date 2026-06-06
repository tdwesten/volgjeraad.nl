<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MunicipalitySeeder extends Seeder
{
    public function run(): void
    {
        Municipality::updateOrCreate(
            ['slug' => 'brummen'],
            [
                'name' => 'Gemeente Brummen',
                'ori_index' => 'ori_brummen',
                'timezone' => 'Europe/Amsterdam',
                'active' => true,
                'launch_date' => config('volgjeraad.launch_date')
                    ? Carbon::parse(config('volgjeraad.launch_date'))->toDateString()
                    : Carbon::now()->toDateString(),
                'backfill_recent_meetings' => config('volgjeraad.backfill_recent_meetings', 2),
                'ai_model_summary' => config('volgjeraad.ai.default_summary_model'),
                'ai_model_eval' => config('volgjeraad.ai.default_eval_model'),
                'raad_pattern' => 'raadsvergadering',
                'sender_name' => 'Volgjeraad Brummen',
                'settings' => [
                    // YouTube-kanaal van RTV794/VoorstVeluwezoom (zendt de raad uit).
                    'youtube_channel_id' => env('BRUMMEN_YOUTUBE_CHANNEL_ID', 'UCGc0GMqy0qVntwXlEPTnqaA'),
                ],
            ],
        );
    }
}
