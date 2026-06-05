<?php

namespace Database\Factories;

use App\Enums\VideoStatus;
use App\Models\Meeting;
use App\Models\MeetingVideo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingVideo>
 */
class MeetingVideoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'youtube_video_id' => $this->faker->regexify('[A-Za-z0-9_-]{11}'),
            'video_url' => null,
            'match_confidence' => null,
            'match_reason' => null,
            'candidates' => null,
            'confirmed_at' => null,
            'transcript_text' => null,
            'transcript_source' => null,
            'transcript_error' => null,
            'transcript_fetched_at' => null,
            'status' => VideoStatus::Pending->value,
            'match_attempts' => 0,
            'transcript_attempts' => 0,
            'last_attempt_at' => null,
        ];
    }

    public function transcribed(): static
    {
        return $this->state([
            'status' => VideoStatus::Transcribed->value,
            'transcript_text' => 'Voorzitter: ik open de vergadering.',
            'transcript_source' => 'supadata:auto',
            'transcript_fetched_at' => now(),
            'confirmed_at' => now(),
        ]);
    }
}
