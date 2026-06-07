<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMunicipalityRequestRequest;
use App\Mail\MunicipalityRequestedMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class MunicipalityRequestController extends Controller
{
    public function store(StoreMunicipalityRequestRequest $request): RedirectResponse
    {
        $mail = new MunicipalityRequestedMail(
            $request->validated('municipality'),
            $request->validated('email'),
        );

        $admins = User::where('is_admin', true)->pluck('email');

        foreach ($admins as $adminEmail) {
            Mail::to($adminEmail)->send($mail);
        }

        // Terugkoppeling gebeurt inline in het formulier (wasSuccessful), net als bij
        // de nieuwsbrief-aanmelding — geen flash zodat het op elke pagina werkt.
        return back();
    }
}
