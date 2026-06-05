<?php

namespace Database\Factories;

use App\Models\AiUsageRecord;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageRecord>
 */
class AiUsageRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'meeting_id' => null,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt_version' => 'v1',
            'operation' => 'agenda_summary',
            'input_tokens' => $this->faker->numberBetween(500, 5000),
            'output_tokens' => $this->faker->numberBetween(200, 2000),
            'cost_cents' => $this->faker->numberBetween(1, 50),
            'status' => 'ok',
            'raw_metadata' => null,
        ];
    }
}
