<?php

namespace App\Models;

use App\Enums\IngestMode;
use App\Enums\MeetingProcessingStatus;
use App\Enums\MeetingType;
use App\Enums\SummaryStatus;
use App\Enums\VideoStatus;
use Carbon\CarbonInterface;
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

    public const SOURCE_TRANSCRIPT = 'transcript';

    public const SOURCE_NOTULE = 'notule';

    public const SKIP_NO_SOURCE = 'no_source';

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
            'source_resolved_at' => 'datetime',
            'notule_detected_at' => 'datetime',
            'notule_checked_at' => 'datetime',
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

    public function processingStatus(): MeetingProcessingStatus
    {
        $launchDate = $this->municipality->launch_date;
        $isPreLaunch = ! $this->shouldSummarize()
            && $launchDate !== null
            && $this->starts_at !== null
            && $this->starts_at->lessThan($launchDate);

        if ($isPreLaunch) {
            return MeetingProcessingStatus::PreLaunch;
        }

        if (! $this->shouldSummarize()) {
            return MeetingProcessingStatus::Scheduled;
        }

        $hasPublished = $this->summaries()
            ->where('status', SummaryStatus::Published->value)
            ->exists();
        if ($hasPublished) {
            return MeetingProcessingStatus::Published;
        }

        if ($this->summary_skipped_reason !== null) {
            return MeetingProcessingStatus::NoSource;
        }

        if ($this->summarized_at !== null) {
            return MeetingProcessingStatus::InReview;
        }

        if ($this->starts_at === null || now()->lessThan($this->starts_at)) {
            return MeetingProcessingStatus::Scheduled;
        }

        $channelId = $this->municipality->settings['youtube_channel_id'] ?? null;
        $isCouncilWithChannel = $this->type === MeetingType::Council && $channelId !== null;

        if ($isCouncilWithChannel
            && now()->lessThan($this->videoReadyAt())) {
            return MeetingProcessingStatus::AwaitingVideo;
        }

        if ($isCouncilWithChannel && $this->video !== null
            && ! in_array($this->video->status, [VideoStatus::NotFound, VideoStatus::Failed], true)) {
            return MeetingProcessingStatus::Processing;
        }

        return MeetingProcessingStatus::AwaitingNotule;
    }

    public function videoReadyAt(): ?CarbonInterface
    {
        return $this->starts_at?->copy()->addHours((int) config('volgjeraad.youtube.video_wait_hours'));
    }

    public function summaryStatusLabel(): string
    {
        $summaries = $this->relationLoaded('summaries')
            ? $this->summaries
            : $this->summaries()->get();

        if ($summaries->isEmpty()) {
            if ($this->summary_skipped_reason !== null) {
                return 'Geen';
            }

            if ($this->shouldSummarize() && $this->processingStatus() !== MeetingProcessingStatus::Published) {
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
