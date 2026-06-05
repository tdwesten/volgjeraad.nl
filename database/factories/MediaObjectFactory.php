<?php

namespace Database\Factories;

use App\Models\AgendaItem;
use App\Models\MediaObject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaObject>
 */
class MediaObjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agenda_item_id' => AgendaItem::factory(),
            'ori_id' => (string) Str::uuid(),
            'position' => $this->faker->randomFloat(2, 1, 10),
            'name' => $this->faker->sentence(3),
            'file_name' => Str::slug($this->faker->words(2, true)).'.pdf',
            'content_type' => 'application/pdf',
            'size_in_bytes' => $this->faker->numberBetween(10000, 5000000),
            'url' => $this->faker->url(),
            'original_url' => $this->faker->url(),
            'text' => null,
            'md_text' => null,
            'text_pages' => null,
            'has_text' => false,
            'text_missing_reason' => null,
            'raw_payload_hash' => hash('sha256', (string) Str::uuid()),
        ];
    }

    public function withText(): static
    {
        $text = $this->faker->paragraphs(3, true);

        return $this->state([
            'text' => $text,
            'md_text' => $text,
            'text_pages' => [$this->faker->paragraph(), $this->faker->paragraph()],
            'has_text' => true,
            'text_missing_reason' => null,
        ]);
    }

    public function empty(): static
    {
        return $this->state([
            'text' => null,
            'md_text' => null,
            'text_pages' => null,
            'has_text' => false,
            'text_missing_reason' => 'ori_text_empty',
        ]);
    }
}
