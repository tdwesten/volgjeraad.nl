<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscriber;

class ConfirmSubscription
{
    public function handle(string $token): Subscriber
    {
        $subscriber = Subscriber::where('confirmation_token', $token)->firstOrFail();

        if ($subscriber->confirmed_at === null) {
            $subscriber->update(['confirmed_at' => now()]);
        }

        return $subscriber;
    }
}
