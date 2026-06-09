<?php

namespace App\Http\Controllers\Public;

use App\Enums\SummaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Municipality;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalityController extends Controller
{
    public function show(Municipality $municipality): Response
    {
        $meetings = $municipality->meetings()
            ->with(['municipality', 'video', 'summaries' => fn ($q) => $q->where('status', SummaryStatus::Published)])
            ->where(function ($q): void {
                $q->whereHas('summaries', fn ($s) => $s->where('status', SummaryStatus::Published))
                    ->orWhere('starts_at', '<=', now());
            })
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get()
            ->filter(fn (Meeting $m) => $m->processingStatus()->isPubliclyVisible())
            ->values();

        return Inertia::render('Municipality/Show', [
            'municipality' => $municipality->only('id', 'slug', 'name'),
            'meetings' => $meetings->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'processing_status' => $meeting->processingStatus()->value,
                'status_message' => $meeting->processingStatus()->publicMessage(),
                'summaries' => $meeting->summaries->map(fn ($s) => [
                    'id' => $s->id,
                    'level' => $s->level->value,
                    'title' => $s->title,
                    'body' => $s->body,
                ])->values(),
            ])->values(),
        ]);
    }

    public function archive(Municipality $municipality): Response
    {
        $meetings = $municipality->meetings()
            ->orderByDesc('starts_at')
            ->get(['id', 'name', 'starts_at', 'type', 'ingest_mode']);

        return Inertia::render('Municipality/Archive', [
            'municipality' => $municipality->only('id', 'slug', 'name'),
            'meetings' => $meetings->map(fn ($meeting) => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'type' => $meeting->type->value,
            ])->values(),
        ]);
    }
}
