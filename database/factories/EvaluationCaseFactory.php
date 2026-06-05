<?php

namespace Database\Factories;

use App\Models\EvaluationCase;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCase>
 */
class EvaluationCaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'name' => $this->faker->sentence(3),
            'source_text' => $this->faker->paragraphs(5, true),
            'expected_facts' => [$this->faker->sentence(), $this->faker->sentence()],
            'forbidden_claims' => null,
            'level' => 'standard',
            'active' => true,
        ];
    }
}
