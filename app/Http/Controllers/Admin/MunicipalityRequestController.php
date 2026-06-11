<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MunicipalityRequest;
use Inertia\Inertia;
use Inertia\Response;

class MunicipalityRequestController extends Controller
{
    public function index(): Response
    {
        $requests = MunicipalityRequest::latest()
            ->paginate(50)
            ->through(fn (MunicipalityRequest $r): array => [
                'id' => $r->id,
                'municipality' => $r->municipality,
                'email' => $r->email,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/MunicipalityRequests/Index', [
            'pageTitle' => 'Gemeente-aanvragen',
            'requests' => $requests,
        ]);
    }
}
