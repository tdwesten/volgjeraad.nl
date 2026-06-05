<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscriber;

class DeleteSubscriberData
{
    public function handle(Subscriber $subscriber): void
    {
        $subscriber->delete();
    }
}
