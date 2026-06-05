<?php

namespace Database\Factories;

use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Municipality>
 */
class MunicipalityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Str::slug($this->faker->unique()->words(2, true)),
            'name' => $this->faker->city(),
            'ori_index' => 'ori_'.$this->faker->word(),
            'timezone' => 'Europe/Amsterdam',
            'active' => true,
            'launch_date' => null,
            'backfill_recent_meetings' => 2,
            'ai_model_summary' => 'gpt-4o-mini',
            'ai_model_eval' => 'gpt-4o-mini',
            'raad_pattern' => 'raadsvergadering',
            'sender_name' => null,
            'settings' => null,
        ];
    }
}
