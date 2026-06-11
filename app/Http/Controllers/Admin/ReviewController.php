<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\RegenerateMeeting;
use App\Actions\Newsletters\PublishMeetingSummaries;
use App\Enums\NewsletterStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Newsletter;
use App\Models\Summary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(): Response
    {
        $newsletters = Newsletter::where('status', NewsletterStatus::Draft)
            ->with(['meeting.municipality', 'summaries'])
            ->get()
            ->map(function (Newsletter $newsletter): array {
                $hasLowConfidence = $newsletter->summaries->contains(
                    fn (Summary $s): bool => $s->confidence !== null && $s->confidence < config('volgjeraad.ai.confidence_highlight_threshold', 60)
                );

                return [
                    'id' => $newsletter->id,
                    'subject' => $newsletter->subject,
                    'meeting' => $newsletter->meeting ? [
                        'id' => $newsletter->meeting->id,
                        'name' => $newsletter->meeting->name,
                        'starts_at' => $newsletter->meeting->starts_at?->toIso8601String(),
                        'municipality' => $newsletter->meeting->municipality->only('id', 'name', 'slug'),
                    ] : null,
                    'low_confidence' => $hasLowConfidence,
                ];
            });

        return Inertia::render('admin/Review/Index', [
            'pageTitle' => 'Review',
            'newsletters' => $newsletters,
        ]);
    }

    public function show(Meeting $meeting): RedirectResponse
    {
        // De review-detail is vervangen door de beheer-meetingpagina onder de gemeente.
        return redirect()->route('admin.municipalities.meetings.show', [$meeting->municipality_id, $meeting]);
    }

    public function update(Request $request, Summary $summary): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
        ]);

        $summary->update($validated);

        return back()->with('success', 'Samenvatting bijgewerkt.');
    }

    public function approve(Meeting $meeting, PublishMeetingSummaries $action): RedirectResponse
    {
        $action->handle($meeting);

        return redirect()->route('admin.municipalities.meetings.show', [$meeting->municipality_id, $meeting])
            ->with('success', 'Nieuwsbrief goedgekeurd en verstuurd.');
    }

    public function regenerate(Meeting $meeting, RegenerateMeeting $action): RedirectResponse
    {
        $action->handle($meeting);

        return redirect()->route('admin.municipalities.meetings.show', [$meeting->municipality_id, $meeting])
            ->with('success', 'Vergadering wordt opnieuw verwerkt.');
    }
}
