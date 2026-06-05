<?php

namespace App\Actions\Summaries;

use App\Models\Meeting;

class CheckMeetingCost
{
    public function handle(Meeting $meeting, string $model): int
    {
        return (int) $meeting->aiUsageRecords()
            ->where('model', $model)
            ->sum('cost_cents');
    }
}
