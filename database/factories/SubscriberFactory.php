<?php

namespace Database\Factories;

use App\Models\Municipality;
use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'municipality_id' => Municipality::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'level' => 'standard',
            'language' => 'nl',
            'confirmation_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
            'confirmed_at' => null,
            'unsubscribed_at' => null,
            'lettermint_contact_id' => null,
            'consent_ip' => null,
            'consent_user_agent' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state([
            'confirmed_at' => now(),
            'unsubscribed_at' => null,
        ]);
    }

    public function unconfirmed(): static
    {
        return $this->state([
            'confirmed_at' => null,
        ]);
    }
}
