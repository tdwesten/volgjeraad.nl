<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Subscriptions\DeleteSubscriberData;
use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(): Response
    {
        $subscribers = Subscriber::with('municipality')
            ->latest()
            ->paginate(50)
            ->through(fn (Subscriber $s): array => [
                'id' => $s->id,
                'email' => $s->email,
                'level' => $s->level,
                'language' => $s->language,
                'municipality' => $s->municipality->only('id', 'name'),
                'confirmed_at' => $s->confirmed_at?->toIso8601String(),
                'unsubscribed_at' => $s->unsubscribed_at?->toIso8601String(),
                'created_at' => $s->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/Subscribers/Index', [
            'subscribers' => $subscribers,
        ]);
    }

    public function destroy(Subscriber $subscriber, DeleteSubscriberData $action): RedirectResponse
    {
        $action->handle($subscriber);

        return back()->with('success', 'Abonnee verwijderd.');
    }

    public function export(): StreamedResponse
    {
        $subscribers = Subscriber::with('municipality')->get();

        return response()->stream(function () use ($subscribers): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['email', 'gemeente', 'niveau', 'taal', 'bevestigd_op', 'aangemeld_op', 'uitgeschreven_op', 'consent_ip']);

            foreach ($subscribers as $subscriber) {
                fputcsv($handle, [
                    $subscriber->email,
                    $subscriber->municipality->name,
                    $subscriber->level,
                    $subscriber->language,
                    $subscriber->confirmed_at?->toIso8601String(),
                    $subscriber->created_at->toIso8601String(),
                    $subscriber->unsubscribed_at?->toIso8601String(),
                    $subscriber->consent_ip,
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="subscribers.csv"',
        ]);
    }
}
