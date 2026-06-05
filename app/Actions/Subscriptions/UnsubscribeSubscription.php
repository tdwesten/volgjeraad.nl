<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscriber;

class UnsubscribeSubscription
{
    public function handle(string $token): Subscriber
    {
        $subscriber = Subscriber::where('unsubscribe_token', $token)->firstOrFail();

        if ($subscriber->unsubscribed_at === null) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return $subscriber;
    }
}
