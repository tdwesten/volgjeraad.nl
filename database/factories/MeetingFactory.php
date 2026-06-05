<?php

namespace Database\Factories;

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Models\Meeting;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'ori_id' => (string) Str::uuid(),
            'type' => MeetingType::Other->value,
            'committee_ori_id' => null,
            'committee_name' => null,
            'name' => $this->faker->sentence(3),
            'starts_at' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'status' => null,
            'source_url' => null,
            'raw_payload' => ['@type' => 'Meeting'],
            'raw_payload_hash' => hash('sha256', (string) Str::uuid()),
            'ingest_mode' => IngestMode::MetadataOnly->value,
            'last_seen_at' => now(),
            'agenda_ingested_at' => null,
            'summarized_at' => null,
        ];
    }

    public function council(): static
    {
        return $this->state([
            'type' => MeetingType::Council->value,
            'committee_name' => 'Raadsvergadering gemeente '.$this->faker->city(),
        ]);
    }

    public function summarizable(): static
    {
        return $this->state([
            'ingest_mode' => IngestMode::Summarize->value,
            'type' => MeetingType::Council->value,
        ]);
    }
}
