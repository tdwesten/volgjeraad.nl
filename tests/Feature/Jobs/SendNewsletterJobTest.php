<?php

use App\Actions\Newsletters\SendNewsletter;
use App\Enums\NewsletterStatus;
use App\Enums\SummaryLevel;
use App\Jobs\SendNewsletterJob;
use App\Mail\NewsletterMail;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function makeNewsletterWithSummaries(): Newsletter
{
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $newsletter = Newsletter::factory()->create([
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => NewsletterStatus::Approved->value,
    ]);

    foreach (SummaryLevel::cases() as $level) {
        $summary = Summary::factory()->create([
            'summarizable_type' => Meeting::class,
            'summarizable_id' => $meeting->id,
            'municipality_id' => $municipality->id,
            'meeting_id' => $meeting->id,
            'level' => $level->value,
            'status' => 'published',
        ]);
        $newsletter->summaries()->attach($summary->id, ['position' => match ($level->value) {
            'standard' => 1,
            'simple' => 2,
            default => 99,
        }]);
    }

    return $newsletter->fresh();
}

test('sends mail to confirmed subscribers of matching level', function (): void {
    Mail::fake();

    $newsletter = makeNewsletterWithSummaries();

    $standardSubscriber = Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
    ]);
    $simpleSubscriber = Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Simple->value,
    ]);

    (new SendNewsletterJob($newsletter->id))->handle(new SendNewsletter);

    Mail::assertSent(NewsletterMail::class, fn ($m) => $m->hasTo($standardSubscriber->email));
    Mail::assertSent(NewsletterMail::class, fn ($m) => $m->hasTo($simpleSubscriber->email));
});

test('does not send to unconfirmed subscribers', function (): void {
    Mail::fake();

    $newsletter = makeNewsletterWithSummaries();

    Subscriber::factory()->unconfirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
    ]);

    (new SendNewsletterJob($newsletter->id))->handle(new SendNewsletter);

    Mail::assertNothingSent();
});

test('does not send to unsubscribed subscribers', function (): void {
    Mail::fake();

    $newsletter = makeNewsletterWithSummaries();

    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
        'unsubscribed_at' => now(),
    ]);

    (new SendNewsletterJob($newsletter->id))->handle(new SendNewsletter);

    Mail::assertNothingSent();
});

test('standard subscribers receive standard summaries only', function (): void {
    Mail::fake();

    $newsletter = makeNewsletterWithSummaries();

    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
    ]);

    (new SendNewsletterJob($newsletter->id))->handle(new SendNewsletter);

    Mail::assertSent(NewsletterMail::class, function (NewsletterMail $mail): bool {
        return $mail->level === SummaryLevel::Standard
            && collect($mail->summaries)->every(fn ($s) => $s->level === SummaryLevel::Standard);
    });
});

test('updates recipients_count and sent status after sending', function (): void {
    Mail::fake();

    $newsletter = makeNewsletterWithSummaries();

    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
    ]);
    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Simple->value,
    ]);

    (new SendNewsletterJob($newsletter->id))->handle(new SendNewsletter);

    $fresh = $newsletter->fresh();
    expect($fresh->status)->toBe(NewsletterStatus::Sent);
    expect($fresh->recipients_count)->toBe(2);
    expect($fresh->sent_at)->not->toBeNull();
});

test('idempotent — already sent newsletter is not resent', function (): void {
    Mail::fake();

    $newsletter = makeNewsletterWithSummaries();
    $newsletter->update(['status' => NewsletterStatus::Sent->value]);

    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
    ]);

    (new SendNewsletterJob($newsletter->id))->handle(new SendNewsletter);

    Mail::assertNothingSent();
});

test('newsletter mail has List-Unsubscribe header', function (): void {
    $newsletter = makeNewsletterWithSummaries();
    $newsletter->load('municipality');

    $subscriber = Subscriber::factory()->confirmed()->create([
        'municipality_id' => $newsletter->municipality_id,
        'level' => SummaryLevel::Standard->value,
    ]);

    $mail = new NewsletterMail($newsletter, SummaryLevel::Standard, $subscriber, []);
    $headers = $mail->headers()->text;

    expect($headers)->toHaveKey('List-Unsubscribe');
    expect($headers['List-Unsubscribe'])->toContain($subscriber->unsubscribe_token);
});
