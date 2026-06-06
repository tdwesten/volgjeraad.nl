<?php

namespace App\Models;

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Enums\SummaryStatus;
use App\Enums\VideoStatus;
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

    /** @return HasOne<MeetingVideo, $this> */
    public function video(): HasOne
    {
        return $this->hasOne(MeetingVideo::class);
    }

    /**
     * Is de transcript-resolutie klaar? Dat is zo wanneer er geen transcript wordt
     * verwacht (niet-raad), het transcript binnen is (Transcribed), het definitief is
     * opgegeven (Failed op de attempt-limiet), of de wachttijd is verstreken. De
     * lifecycle-gate (DispatchMeetingSummariesIfReady) leunt hierop.
     */
    public function transcriptResolved(): bool
    {
        if ($this->type !== MeetingType::Council) {
            return true;
        }

        $video = $this->video;

        if ($video?->status === VideoStatus::Transcribed) {
            return true;
        }

        if ($video?->status === VideoStatus::Failed
            && $video->transcript_attempts >= (int) config('volgjeraad.youtube.max_transcript_attempts')) {
            return true;
        }

        $waitDays = (int) config('volgjeraad.youtube.transcript_wait_days');

        return $this->starts_at !== null
            && now()->greaterThanOrEqualTo($this->starts_at->copy()->addDays($waitDays));
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

    /** @return HasMany<ProcessingLog, $this> */
    public function processingLogs(): HasMany
    {
        return $this->hasMany(ProcessingLog::class);
    }

    public function shouldSummarize(): bool
    {
        return $this->ingest_mode === IngestMode::Summarize;
    }

    public function summaryStatusLabel(): string
    {
        $summaries = $this->relationLoaded('summaries')
            ? $this->summaries
            : $this->summaries()->get();

        if ($summaries->isEmpty()) {
            if ($this->type === MeetingType::Council && ! $this->transcriptResolved()) {
                return 'Wacht op verwerking';
            }

            return 'Geen';
        }

        if ($summaries->contains(fn (Summary $s): bool => $s->status === SummaryStatus::Published)) {
            return 'Gepubliceerd';
        }

        if ($summaries->contains(fn (Summary $s): bool => $s->status === SummaryStatus::Approved)) {
            return 'Goedgekeurd';
        }

        return 'Concept';
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
