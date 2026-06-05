<?php

namespace Database\Factories;

use App\Enums\EvaluationStatus;
use App\Models\EvaluationCase;
use App\Models\EvaluationRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationRun>
 */
class EvaluationRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_case_id' => EvaluationCase::factory(),
            'prompt_version' => 'v1',
            'model' => 'gpt-4o-mini',
            'status' => EvaluationStatus::Passed->value,
            'score' => $this->faker->numberBetween(60, 100),
            'checklist_results' => [
                ['fact' => $this->faker->sentence(), 'found' => true],
            ],
            'judge_feedback' => $this->faker->sentence(),
        ];
    }
}
