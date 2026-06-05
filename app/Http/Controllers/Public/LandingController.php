<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
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
        ]);
    }
}
