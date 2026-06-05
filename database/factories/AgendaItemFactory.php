<?php

namespace Database\Factories;

use App\Models\AgendaItem;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AgendaItem>
 */
class AgendaItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'ori_id' => (string) Str::uuid(),
            'position' => $this->faker->randomFloat(2, 1, 20),
            'name' => $this->faker->sentence(4),
            'raw_payload' => ['@type' => 'AgendaItem'],
            'raw_payload_hash' => hash('sha256', (string) Str::uuid()),
            'last_seen_at' => now(),
            'attachments_fetched_at' => null,
        ];
    }
}
