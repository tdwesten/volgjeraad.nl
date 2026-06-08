<?php

namespace App\Jobs;

use App\Actions\Ingest\IngestAgendaMediaObjects;
use App\Models\AgendaItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestAgendaMediaObjectsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $agendaItemId) {}

    public function handle(IngestAgendaMediaObjects $action): void
    {
        Log::info('IngestAgendaMediaObjectsJob gestart', ['agenda_item_id' => $this->agendaItemId]);

        $agendaItem = AgendaItem::findOrFail($this->agendaItemId);
        $action->handle($agendaItem);

        Log::info('IngestAgendaMediaObjectsJob klaar', ['agenda_item_id' => $this->agendaItemId]);
    }

    /** @return array<int, mixed> */
    public function middleware(): array
    {
        return [
            new RateLimited('ori'),
            (new ThrottlesExceptions(5, 300))->backoff(60)->by('ori'),
        ];
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('IngestAgendaMediaObjectsJob mislukt', [
            'agenda_item_id' => $this->agendaItemId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
