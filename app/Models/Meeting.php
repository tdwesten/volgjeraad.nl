<?php

namespace App\Models;

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'type' => MeetingType::class,
            'ingest_mode' => IngestMode::class,
            'starts_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'agenda_ingested_at' => 'datetime',
            'summarized_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Municipality, $this> */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /** @return HasMany<AgendaItem, $this> */
    public function agendaItems(): HasMany
    {
        return $this->hasMany(AgendaItem::class);
    }

    /** @return MorphMany<Summary, $this> */
    public function summaries(): MorphMany
    {
        return $this->morphMany(Summary::class, 'summarizable');
    }

    /** @return HasOne<Newsletter, $this> */
    public function newsletter(): HasOne
    {
        return $this->hasOne(Newsletter::class);
    }

    /** @return HasMany<AiUsageRecord, $this> */
    public function aiUsageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    public function shouldSummarize(): bool
    {
        return $this->ingest_mode === IngestMode::Summarize;
    }

    /** @param Builder<Meeting> $query */
    public function scopeCouncil(Builder $query): void
    {
        $query->where('type', MeetingType::Council->value);
    }

    /** @param Builder<Meeting> $query */
    public function scopeSummarizable(Builder $query): void
    {
        $query->where('ingest_mode', IngestMode::Summarize->value);
    }
}
