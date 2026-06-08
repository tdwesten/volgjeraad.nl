<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Meetings\RegenerateMeeting;
use App\Actions\Municipalities\FindMunicipalityStream;
use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMunicipalityRequest;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use App\Services\Ori\OriClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    public function create(): Response
    {
        return Inertia::render('admin/Municipalities/Create');
    }

    public function store(StoreMunicipalityRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $settings = [];
        if ($request->filled('youtube_channel_id')) {
            $settings['youtube_channel_id'] = (string) $request->string('youtube_channel_id');
        }

        $municipality = Municipality::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'ori_index' => $validated['ori_index'],
            'timezone' => $validated['timezone'] ?? 'Europe/Amsterdam',
            'active' => $request->boolean('active'),
            'settings' => $settings !== [] ? $settings : null,
        ]);

        return redirect()
            ->route('admin.municipalities.show', $municipality)
            ->with('success', "Gemeente {$municipality->name} is toegevoegd.");
    }

    public function show(Municipality $municipality, OriClient $oriClient): Response
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

        $oriStatus = Cache::remember(
            "admin:municipality:{$municipality->id}:ori_probe:{$municipality->ori_index}",
            now()->addMinutes(10),
            fn (): array => $oriClient->probeIndex($municipality->ori_index),
        );

        return Inertia::render('admin/Municipalities/Show', [
            'municipality' => [
                'id' => $municipality->id,
                'name' => $municipality->name,
                'slug' => $municipality->slug,
                'active' => $municipality->active,
                'ori_index' => $municipality->ori_index,
                'youtube_channel_id' => ($municipality->settings['youtube_channel_id'] ?? null),
            ],
            'ori_status' => $oriStatus,
            'meetings' => $meetings,
        ]);
    }

    public function processMeeting(Municipality $municipality, Meeting $meeting, RegenerateMeeting $action): RedirectResponse
    {
        abort_unless($meeting->municipality_id === $municipality->id, 404);

        $action->handle($meeting);

        return back()->with('success', "Verwerking gestart voor '{$meeting->name}'.");
    }

    public function toggleActive(Municipality $municipality): RedirectResponse
    {
        $municipality->update(['active' => ! $municipality->active]);

        return back()->with('success', $municipality->active
            ? "{$municipality->name} is geactiveerd."
            : "{$municipality->name} is gedeactiveerd.");
    }

    public function validateOri(Request $request, OriClient $oriClient): JsonResponse
    {
        $validated = $request->validate([
            'ori_index' => ['required', 'string', 'max:255', 'regex:/^ori_[a-z0-9._-]+$/'],
        ]);

        return response()->json($oriClient->probeIndex($validated['ori_index']));
    }

    public function findStream(Request $request, FindMunicipalityStream $finder): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json($finder->handle($validated['name']));
    }

    public function updateChannel(Request $request, Municipality $municipality): RedirectResponse
    {
        $validated = $request->validate([
            'youtube_channel_id' => ['nullable', 'string', 'regex:/^UC[A-Za-z0-9_-]{22}$/'],
        ]);

        $settings = $municipality->settings ?? [];

        if (! empty($validated['youtube_channel_id'])) {
            $settings['youtube_channel_id'] = $validated['youtube_channel_id'];
        } else {
            unset($settings['youtube_channel_id']);
        }

        $municipality->update(['settings' => $settings !== [] ? $settings : null]);

        return back()->with('success', 'YouTube-kanaal opgeslagen.');
    }
}
