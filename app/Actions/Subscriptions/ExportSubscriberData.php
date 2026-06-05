<?php

namespace App\Actions\Subscriptions;

use App\Models\Subscriber;

class ExportSubscriberData
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Subscriber $subscriber): array
    {
        return [
            'email' => $subscriber->email,
            'municipality' => $subscriber->municipality->name,
            'level' => $subscriber->level,
            'language' => $subscriber->language,
            'confirmed_at' => $subscriber->confirmed_at?->toIso8601String(),
            'subscribed_at' => $subscriber->created_at->toIso8601String(),
            'unsubscribed_at' => $subscriber->unsubscribed_at?->toIso8601String(),
            'consent_ip' => $subscriber->consent_ip,
        ];
    }
}
