<?php

namespace App\Models;

use App\Enums\VideoStatus;
use Database\Factories\MeetingVideoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingVideo extends Model
{
    /** @use HasFactory<MeetingVideoFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'match_attempts' => 0,
        'transcript_attempts' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'candidates' => 'array',
            'status' => VideoStatus::class,
            'confirmed_at' => 'datetime',
            'transcript_fetched_at' => 'datetime',
            'last_attempt_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
