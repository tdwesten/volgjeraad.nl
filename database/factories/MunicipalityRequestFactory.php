<?php

namespace Database\Factories;

use App\Models\MunicipalityRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MunicipalityRequest>
 */
class MunicipalityRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'municipality' => fake()->city(),
            'email' => fake()->safeEmail(),
        ];
    }
}
