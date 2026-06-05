<?php

namespace Database\Factories;

use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Models\Meeting;
use App\Models\Summary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Summary>
 */
class SummaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $meeting = Meeting::factory()->create();

        return [
            'summarizable_type' => Meeting::class,
            'summarizable_id' => $meeting->id,
            'municipality_id' => $meeting->municipality_id,
            'meeting_id' => $meeting->id,
            'level' => SummaryLevel::Standard->value,
            'language' => 'nl',
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraphs(3, true),
            'impact_note' => null,
            'confidence' => $this->faker->numberBetween(60, 95),
            'status' => SummaryStatus::Draft->value,
            'flags' => null,
            'review_notes' => null,
            'approved_by' => null,
            'reviewed_at' => null,
            'published_at' => null,
            'model' => 'gpt-4o-mini',
            'prompt_version' => 'v1',
            'source_hash' => hash('sha256', $this->faker->uuid()),
            'input_tokens' => $this->faker->numberBetween(500, 5000),
            'output_tokens' => $this->faker->numberBetween(200, 2000),
            'cost_cents' => $this->faker->numberBetween(1, 50),
        ];
    }

    public function standard(): static
    {
        return $this->state(['level' => SummaryLevel::Standard->value]);
    }

    public function simple(): static
    {
        return $this->state(['level' => SummaryLevel::Simple->value]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => SummaryStatus::Approved->value,
            'reviewed_at' => now(),
        ]);
    }

    public function published(): static
    {
        return $this->state([
            'status' => SummaryStatus::Published->value,
            'reviewed_at' => now()->subHour(),
            'published_at' => now(),
        ]);
    }
}
