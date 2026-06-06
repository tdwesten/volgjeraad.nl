<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\ProcessingLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProcessingLog>
 */
class ProcessingLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'municipality_id' => null,
            'step' => $this->faker->randomElement(['ingest', 'agenda', 'media', 'summarize', 'video_match', 'transcript', 'newsletter', 'regenerate']),
            'status' => $this->faker->randomElement(['info', 'success', 'warning', 'error']),
            'message' => $this->faker->sentence(),
            'context' => null,
        ];
    }

    public function forMeeting(Meeting $meeting): static
    {
        return $this->state([
            'meeting_id' => $meeting->id,
            'municipality_id' => $meeting->municipality_id,
        ]);
    }

    public function success(): static
    {
        return $this->state(['status' => 'success']);
    }

    public function error(): static
    {
        return $this->state(['status' => 'error']);
    }
}
