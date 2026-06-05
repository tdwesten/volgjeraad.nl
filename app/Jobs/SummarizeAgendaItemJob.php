<?php

namespace App\Jobs;

use App\Actions\Summaries\GenerateAgendaItemSummary;
use App\Enums\SummaryLevel;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SummarizeAgendaItemJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $agendaItemId,
        public SummaryLevel $level,
    ) {}

    public function handle(GenerateAgendaItemSummary $action): void
    {
        $item = AgendaItem::findOrFail($this->agendaItemId);
        $action->handle($item, $this->level);
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void {}
}
