<?php

namespace App\Jobs;

use App\Actions\Summaries\ResolveMeetingSummarySources;
use App\Enums\IngestMode;
use App\Models\Meeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResolveReadyMeetingsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ResolveMeetingSummarySources $resolve): void
    {
        Log::info('ResolveReadyMeetingsJob gestart');

        $resolved = 0;

        Meeting::query()
            ->where('ingest_mode', IngestMode::Summarize->value)
            ->whereNull('summarized_at')
            ->whereNull('summary_skipped_reason')
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($meetings) use ($resolve, &$resolved): void {
                foreach ($meetings as $row) {
                    $meeting = Meeting::with(['municipality', 'video'])->find($row->id);
                    if ($meeting === null) {
                        continue;
                    }

                    try {
                        $resolve->handle($meeting);
                        $resolved++;
                    } catch (Throwable $e) {
                        Log::warning('ResolveReadyMeetingsJob: meeting mislukt', [
                            'meeting_id' => $meeting->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        Log::info('ResolveReadyMeetingsJob klaar', ['resolved' => $resolved]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ResolveReadyMeetingsJob mislukt', ['exception' => $exception->getMessage()]);
    }
}
