<?php

namespace App\Models;

use Database\Factories\AgendaItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AgendaItem extends Model
{
    /** @use HasFactory<AgendaItemFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'position' => 'decimal:2',
            'last_seen_at' => 'datetime',
            'attachments_fetched_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return HasMany<MediaObject, $this> */
    public function mediaObjects(): HasMany
    {
        return $this->hasMany(MediaObject::class);
    }

    /** @return MorphMany<Summary, $this> */
    public function summaries(): MorphMany
    {
        return $this->morphMany(Summary::class, 'summarizable');
    }

    public function sourceText(): string
    {
        return $this->mediaObjects()
            ->withText()
            ->orderBy('position')
            ->pluck('md_text')
            ->filter()
            ->implode("\n\n");
    }
}
