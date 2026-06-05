<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NewsletterStatus;
use App\Http\Controllers\Controller;
use App\Jobs\IngestMunicipalityMeetingsJob;
use App\Models\AiUsageRecord;
use App\Models\Municipality;
use App\Models\Newsletter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'totalCostCents' => AiUsageRecord::sum('cost_cents'),
            'totalAiCalls' => AiUsageRecord::count(),
            'newslettersSent' => Newsletter::where('status', NewsletterStatus::Sent)->count(),
            'drafts' => Newsletter::where('status', NewsletterStatus::Draft)->count(),
        ]);
    }

    public function ingest(): RedirectResponse
    {
        Municipality::query()->active()->each(
            fn (Municipality $municipality) => IngestMunicipalityMeetingsJob::dispatch($municipality->id),
        );

        return back()->with('success', 'Zoeken naar nieuwe vergaderingen gestart. Resultaten verschijnen zodra de verwerking klaar is.');
    }
}
