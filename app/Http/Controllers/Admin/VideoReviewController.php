<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Videos\ConfirmMeetingVideo;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\MeetingVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VideoReviewController extends Controller
{
    public function index(): Response
    {
        $videos = MeetingVideo::query()
            ->where('status', VideoStatus::NeedsConfirmation->value)
            ->with('meeting.municipality')
            ->get()
            ->map(fn (MeetingVideo $video): array => [
                'id' => $video->id,
                'match_confidence' => $video->match_confidence,
                'match_reason' => $video->match_reason,
                'candidates' => $video->candidates ?? [],
                'meeting' => $video->meeting ? [
                    'id' => $video->meeting->id,
                    'name' => $video->meeting->name,
                    'starts_at' => $video->meeting->starts_at?->toIso8601String(),
                    'municipality' => $video->meeting->municipality->only('id', 'name', 'slug'),
                ] : null,
            ]);

        return Inertia::render('admin/Videos/Index', [
            'videos' => $videos,
        ]);
    }

    public function confirm(Request $request, MeetingVideo $video, ConfirmMeetingVideo $action): RedirectResponse
    {
        $validated = $request->validate([
            'video_id' => ['required', 'string'],
        ]);

        try {
            $action->handle($video, $validated['video_id']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['video_id' => $e->getMessage()]);
        }

        return redirect('/admin/videos')->with('success', 'Video bevestigd; transcript wordt opgehaald.');
    }
}
