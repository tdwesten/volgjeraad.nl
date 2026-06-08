<?php

namespace App\Jobs;

use App\Actions\Summaries\GenerateMeetingSummary;
use App\Enums\SummaryLevel;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SummarizeMeetingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $meetingId,
        public SummaryLevel $level,
    ) {}

    public function handle(GenerateMeetingSummary $action): void
    {
        Log::info('SummarizeMeetingJob gestart', [
            'meeting_id' => $this->meetingId,
            'level' => $this->level->value,
        ]);

        $meeting = Meeting::findOrFail($this->meetingId);
        $action->handle($meeting, $this->level);

        // After generating meeting summary, dispatch newsletter composition if all levels done
        $allLevels = SummaryLevel::cases();
        $allDone = collect($allLevels)->every(
            fn ($l) => $meeting->summaries()
                ->where('summarizable_type', $meeting->getMorphClass())
                ->where('level', $l->value)
                ->exists()
        );

        if ($allDone && $meeting->newsletter === null) {
            Log::info('Alle samenvatting-niveaus klaar, nieuwsbrief samenstellen', ['meeting_id' => $this->meetingId]);
            dispatch(new ComposeNewsletterJob($meeting->id));
        }

        Log::info('SummarizeMeetingJob klaar', [
            'meeting_id' => $this->meetingId,
            'level' => $this->level->value,
        ]);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SummarizeMeetingJob mislukt', [
            'meeting_id' => $this->meetingId,
            'level' => $this->level->value,
            'exception' => $exception->getMessage(),
        ]);
    }
}
