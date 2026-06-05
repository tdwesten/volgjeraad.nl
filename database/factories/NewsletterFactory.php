<?php

namespace Database\Factories;

use App\Enums\NewsletterStatus;
use App\Models\Municipality;
use App\Models\Newsletter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Newsletter>
 */
class NewsletterFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $municipality = Municipality::factory()->create();

        return [
            'municipality_id' => $municipality->id,
            'meeting_id' => null,
            'subject' => $this->faker->sentence(),
            'intro' => null,
            'status' => NewsletterStatus::Draft->value,
            'approved_by' => null,
            'approved_at' => null,
            'sent_at' => null,
            'recipients_count' => 0,
        ];
    }
}
