<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\ProcessingLog;
use App\Models\Summary;
use Inertia\Inertia;
use Inertia\Response;

class MeetingController extends Controller
{
    public function show(Municipality $municipality, Meeting $meeting): Response
    {
        abort_unless($meeting->municipality_id === $municipality->id, 404);

        $meeting->load([
            'municipality',
            'summaries',
            'newsletter',
            'agendaItems' => fn ($q) => $q->orderBy('position'),
            'agendaItems.mediaObjects' => fn ($q) => $q->orderBy('position'),
            'video',
        ]);

        $summariesByLevel = $meeting->summaries->keyBy(fn (Summary $s): string => $s->level->value);

        $summaryArray = fn (?Summary $s): ?array => $s ? [
            'id' => $s->id,
            'title' => $s->title,
            'body' => $s->body,
            'confidence' => $s->confidence,
            'status' => $s->status->value,
        ] : null;

        $video = $meeting->video;
        $hasTranscript = $video?->transcript_text !== null && $video?->transcript_text !== '';
        $videoData = $video ? [
            'status' => $video->status->value,
            'youtube_video_id' => $video->youtube_video_id,
            'video_url' => $video->video_url,
            'has_transcript' => $hasTranscript,
            'transcript_source' => $video->transcript_source,
        ] : null;

        $notule = null;
        if ($meeting->notule_media_object_id !== null) {
            $media = MediaObject::find($meeting->notule_media_object_id);
            if ($media !== null) {
                $notule = [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->url,
                    'original_url' => $media->original_url,
                ];
            }
        }

        $status = $meeting->processingStatus();

        $logs = $meeting->processingLogs()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (ProcessingLog $log): array => [
                'id' => $log->id,
                'step' => $log->step,
                'status' => $log->status,
                'message' => $log->message,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->all();

        $newsletter = $meeting->newsletter;

        return Inertia::render('admin/Meetings/Show', [
            'meeting' => [
                'id' => $meeting->id,
                'name' => $meeting->name,
                'type' => $meeting->type->value,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'municipality' => $meeting->municipality->only('id', 'name', 'slug'),
                'processing_status' => $status->value,
                'processing_label' => $status->adminLabel(),
                'summarized_at' => $meeting->summarized_at?->toIso8601String(),
                'source_resolved_at' => $meeting->source_resolved_at?->toIso8601String(),
            ],
            'standardSummary' => $summaryArray($summariesByLevel->get('standard')),
            'simpleSummary' => $summaryArray($summariesByLevel->get('simple')),
            'newsletter' => $newsletter ? [
                'id' => $newsletter->id,
                'subject' => $newsletter->subject,
                'status' => $newsletter->status->value,
            ] : null,
            'sources' => [
                'summary_source' => $meeting->summary_source,
                'summary_skipped_reason' => $meeting->summary_skipped_reason,
                'notule' => $notule,
                'has_transcript' => $hasTranscript,
                'has_video' => $video !== null,
            ],
            'agendaItems' => $meeting->agendaItems->map(fn ($item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'position' => (float) $item->position,
                'mediaObjects' => $item->mediaObjects->map(fn ($media): array => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'url' => $media->url,
                    'original_url' => $media->original_url,
                ])->values(),
            ])->values(),
            'video' => $videoData,
            'logs' => $logs,
        ]);
    }
}
