<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalityOverviewController extends Controller
{
    public function index(): Response
    {
        $municipalities = Municipality::query()
            ->withCount('meetings')
            ->withCount(['subscribers as confirmed_subscribers_count' => fn ($q) => $q->whereNotNull('confirmed_at')])
            ->addSelect([
                'published_summaries_count' => Summary::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('municipality_id', 'municipalities.id')
                    ->where('status', SummaryStatus::Published)
                    ->where('level', '!=', SummaryLevel::Plain->value),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Municipality $municipality): array => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'slug' => $municipality->slug,
                'active' => $municipality->active,
                'meetings_count' => $municipality->meetings_count,
                'confirmed_subscribers_count' => $municipality->confirmed_subscribers_count,
                'published_summaries_count' => (int) $municipality->published_summaries_count,
            ]);

        return Inertia::render('admin/Municipalities/Index', [
            'municipalities' => $municipalities,
        ]);
    }

    public function show(Municipality $municipality): Response
    {
        $meetings = $municipality->meetings()
            ->with(['summaries', 'video'])
            ->latest('starts_at')
            ->get()
            ->map(fn (Meeting $meeting): array => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'type' => $meeting->type->value,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'ingest_mode' => $meeting->ingest_mode->value,
                'summary_status' => $meeting->summaryStatusLabel(),
                'is_summarizable' => $meeting->shouldSummarize(),
                'teaser' => $meeting->summaries
                    ->firstWhere('level', SummaryLevel::Plain)?->body,
            ]);

        return Inertia::render('admin/Municipalities/Show', [
            'municipality' => $municipality->only('id', 'name', 'slug', 'active'),
            'meetings' => $meetings,
        ]);
    }
}
