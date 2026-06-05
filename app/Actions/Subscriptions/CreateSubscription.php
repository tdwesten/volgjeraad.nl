<?php

namespace App\Actions\Subscriptions;

use App\Enums\SummaryLevel;
use App\Mail\ConfirmSubscriptionMail;
use App\Models\Municipality;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CreateSubscription
{
    public function handle(
        Municipality $municipality,
        string $email,
        SummaryLevel $level,
        ?string $ip,
        ?string $ua
    ): Subscriber {
        $existing = Subscriber::where('municipality_id', $municipality->id)
            ->where('email', $email)
            ->first();

        if ($existing) {
            return $existing;
        }

        $subscriber = Subscriber::create([
            'municipality_id' => $municipality->id,
            'email' => $email,
            'level' => $level->value,
            'confirmation_token' => Str::random(64),
            'unsubscribe_token' => Str::random(64),
            'consent_ip' => $ip,
            'consent_user_agent' => $ua,
        ]);

        Mail::to($email)->send(new ConfirmSubscriptionMail($subscriber));

        return $subscriber;
    }
}
