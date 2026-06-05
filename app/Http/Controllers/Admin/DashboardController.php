<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NewsletterStatus;
use App\Http\Controllers\Controller;
use App\Models\AiUsageRecord;
use App\Models\Newsletter;
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
}
