<?php

namespace App\Http\Controllers\Public;

use App\Enums\SummaryStatus;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\Municipality;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function show(Municipality $municipality, Meeting $meeting): Response
    {
        $meeting->load([
            'summaries' => fn ($q) => $q->where('status', SummaryStatus::Published),
            'agendaItems' => fn ($q) => $q->orderBy('position'),
            'agendaItems.mediaObjects' => fn ($q) => $q->orderBy('position'),
            'video',
        ]);

        $summariesByLevel = $meeting->summaries->keyBy(fn ($s) => $s->level->value);

        $video = $meeting->video;
        $videoData = ($video
            && in_array($video->status, [VideoStatus::Matched, VideoStatus::Transcribed], true)
            && $video->youtube_video_id !== null)
            ? [
                'youtube_video_id' => $video->youtube_video_id,
                'video_url' => $video->video_url,
            ]
            : null;

        return Inertia::render('Meeting/Show', [
            'municipality' => $municipality->only('id', 'slug', 'name'),
            'meeting' => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'standard_summary' => ($s = $summariesByLevel->get('standard')) ? [
                    'id' => $s->id,
                    'title' => $s->title,
                    'body' => $s->body,
                ] : null,
                'simple_summary' => ($s = $summariesByLevel->get('simple')) ? [
                    'id' => $s->id,
                    'title' => $s->title,
                    'body' => $s->body,
                ] : null,
            ],
            'agendaItems' => $meeting->agendaItems->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'position' => (float) $item->position,
                'mediaObjects' => $item->mediaObjects->map(fn ($media) => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->url,
                    'original_url' => $media->original_url,
                ])->values(),
            ])->values(),
            'video' => $videoData,
        ]);
    }
}
