<?php

namespace App\Actions\Logging;

use App\Models\Meeting;
use App\Models\ProcessingLog;

class RecordProcessingEvent
{
    public function handle(
        ?Meeting $meeting,
        string $step,
        string $status,
        string $message,
        array $context = [],
        ?int $municipalityId = null,
    ): ProcessingLog {
        return ProcessingLog::create([
            'meeting_id' => $meeting?->id,
            'municipality_id' => $meeting?->municipality_id ?? $municipalityId,
            'step' => $step,
            'status' => $status,
            'message' => $message,
            'context' => empty($context) ? null : $context,
        ]);
    }
}
