<?php

namespace App\Http\Controllers\Public;

use App\Actions\Subscriptions\ConfirmSubscription;
use App\Actions\Subscriptions\CreateSubscription;
use App\Actions\Subscriptions\UnsubscribeSubscription;
use App\Enums\SummaryLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\Municipality;
use Illuminate\Http\RedirectResponse;

class SubscriptionController extends Controller
{
    public function store(StoreSubscriptionRequest $request, CreateSubscription $action): RedirectResponse
    {
        $municipality = Municipality::where('slug', $request->validated('municipality_slug'))->firstOrFail();

        $action->handle(
            $municipality,
            $request->validated('email'),
            SummaryLevel::from($request->validated('level')),
            $request->ip(),
            $request->userAgent(),
        );

        // Geen flash-bericht: het NewsletterSignup-component toont de bevestiging zelf
        // (inline), zodat dit op elke pagina werkt en niet dubbelt met de pagina-flash.
        return back();
    }

    public function confirm(string $token, ConfirmSubscription $action): RedirectResponse
    {
        $subscriber = $action->handle($token);
        $subscriber->load('municipality');

        return redirect("/{$subscriber->municipality->slug}")
            ->with('success', 'Je aanmelding is bevestigd. Welkom!');
    }

    public function unsubscribe(string $token, UnsubscribeSubscription $action): RedirectResponse
    {
        $subscriber = $action->handle($token);
        $subscriber->load('municipality');

        return redirect("/{$subscriber->municipality->slug}")
            ->with('success', 'Je bent uitgeschreven. Je ontvangt geen e-mails meer.');
    }
}
