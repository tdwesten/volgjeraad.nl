<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\RegenerateMeeting;
use App\Actions\Newsletters\PublishMeetingSummaries;
use App\Enums\NewsletterStatus;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Newsletter;
use App\Models\ProcessingLog;
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
            'newsletters' => $newsletters,
        ]);
    }

    public function show(Meeting $meeting): Response
    {
        $meeting->load(['municipality', 'newsletter.summaries', 'video']);

        $newsletter = $meeting->newsletter;

        $std = $newsletter?->summaries->firstWhere('level', 'standard');
        $sim = $newsletter?->summaries->firstWhere('level', 'simple');

        $toArray = fn (?Summary $s): ?array => $s ? [
            'id' => $s->id,
            'title' => $s->title,
            'body' => $s->body,
            'confidence' => $s->confidence,
        ] : null;

        $logs = $meeting->processingLogs()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ProcessingLog $log) => [
                'id' => $log->id,
                'step' => $log->step,
                'status' => $log->status,
                'message' => $log->message,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->all();

        $video = $meeting->video;
        $videoUrl = ($video
            && in_array($video->status, [VideoStatus::Matched, VideoStatus::Transcribed], true)
            && $video->video_url !== null)
            ? $video->video_url
            : null;

        return Inertia::render('admin/Review/Show', [
            'meeting' => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'municipality' => $meeting->municipality->only('id', 'name', 'slug'),
            ],
            'video_url' => $videoUrl,
            'newsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'subject' => $newsletter->subject,
                'status' => $newsletter->status->value,
            ] : null,
            'standardSummary' => $toArray($std),
            'simpleSummary' => $toArray($sim),
            'logs' => $logs,
        ]);
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

        return redirect()->route('admin.review.index')->with('success', 'Nieuwsbrief goedgekeurd en verstuurd.');
    }

    public function regenerate(Meeting $meeting, RegenerateMeeting $action): RedirectResponse
    {
        $action->handle($meeting);

        return redirect()->route('admin.review.index')->with('success', 'Vergadering wordt opnieuw verwerkt.');
    }
}
