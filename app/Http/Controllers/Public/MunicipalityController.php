<?php

namespace App\Http\Controllers\Public;

use App\Enums\SummaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Municipality;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalityController extends Controller
{
    public function show(Municipality $municipality): Response
    {
        $meetings = $municipality->meetings()
            ->whereHas('summaries', fn ($q) => $q->where('status', SummaryStatus::Published))
            ->with(['summaries' => fn ($q) => $q->where('status', SummaryStatus::Published)])
            ->orderByDesc('starts_at')
            ->limit(20)
            ->get();

        return Inertia::render('Municipality/Show', [
            'municipality' => $municipality->only('id', 'slug', 'name'),
            'meetings' => $meetings->map(fn ($meeting) => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
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
