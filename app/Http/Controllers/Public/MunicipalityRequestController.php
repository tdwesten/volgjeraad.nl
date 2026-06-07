<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMunicipalityRequestRequest;
use App\Mail\MunicipalityRequestedMail;
use App\Models\MunicipalityRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class MunicipalityRequestController extends Controller
{
    public function store(StoreMunicipalityRequestRequest $request): RedirectResponse
    {
        // Honeypot: bots vullen het verborgen 'website'-veld in. Stil negeren.
        if ($request->filled('website')) {
            return back();
        }

        $name = trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $request->validated('municipality'))));

        MunicipalityRequest::create([
            'municipality' => $name,
            'email' => $request->validated('email'),
        ]);

        // Verse mailable per ontvanger zodat de queued instance niet hergebruikt
        // wordt (anders lekt de eerste 'to' door naar volgende ontvangers).
        foreach (User::where('is_admin', true)->pluck('email') as $adminEmail) {
            Mail::to($adminEmail)->queue(new MunicipalityRequestedMail($name, $request->validated('email')));
        }

        // Terugkoppeling gebeurt inline in het formulier (wasSuccessful), net als bij
        // de nieuwsbrief-aanmelding — geen flash zodat het op elke pagina werkt.
        return back();
    }
}
