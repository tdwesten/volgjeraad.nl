<?php

namespace App\Http\Controllers\Public;

use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Municipality;
use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $municipalities = Municipality::active()
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return Inertia::render('Landing', [
            'municipalities' => $municipalities,
            'featuredMeeting' => $this->featuredMeeting(),
        ]);
    }

    /**
     * Een recente, gepubliceerde vergadering van een willekeurige actieve gemeente,
     * als laagdrempelig voorbeeld op de voorpagina.
     *
     * @return array<string, mixed>|null
     */
    private function featuredMeeting(): ?array
    {
        $publishedMeetings = fn ($query) => $query->whereHas(
            'summaries',
            fn ($q) => $q->where('status', SummaryStatus::Published)
        );

        $municipalityId = Meeting::query()
            ->whereHas('municipality', fn ($q) => $q->where('active', true))
            ->tap($publishedMeetings)
            ->distinct()
            ->pluck('municipality_id');

        if ($municipalityId->isEmpty()) {
            return null;
        }

        $meeting = Meeting::query()
            ->where('municipality_id', $municipalityId->random())
            ->tap($publishedMeetings)
            ->with([
                'municipality:id,slug,name',
                'summaries' => fn ($q) => $q->where('status', SummaryStatus::Published),
            ])
            ->latest('starts_at')
            ->first();

        if ($meeting === null) {
            return null;
        }

        $summaries = $meeting->summaries->keyBy(fn ($s) => $s->level->value);
        $teaser = $summaries->get(SummaryLevel::Plain->value)?->body
            ?? $summaries->get(SummaryLevel::Standard->value)?->body;

        return [
            'id' => $meeting->id,
            'name' => $meeting->name,
            'starts_at' => $meeting->starts_at?->toIso8601String(),
            'teaser' => $teaser,
            'municipality' => [
                'slug' => $meeting->municipality->slug,
                'name' => $meeting->municipality->name,
            ],
        ];
    }
}
