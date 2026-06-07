<?php

use App\Actions\Newsletters\ComposeNewsletter;
use App\Enums\NewsletterStatus;
use App\Enums\SummaryLevel;
use App\Mail\ReviewReadyMail;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function meetingWithMeetingSummaries(): Meeting
{
    $municipality = Municipality::factory()->create(['launch_date' => '2026-01-01']);
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    foreach (SummaryLevel::cases() as $level) {
        Summary::create([
            'summarizable_type' => $meeting->getMorphClass(),
            'summarizable_id' => $meeting->id,
            'municipality_id' => $municipality->id,
            'meeting_id' => $meeting->id,
            'level' => $level->value,
            'language' => 'nl',
            'source_hash' => 'hash-meeting-'.$level->value,
            'status' => 'draft',
            'title' => 'Title '.$level->value,
            'body' => 'Body',
            'input_tokens' => 0,
            'output_tokens' => 0,
            'prompt_version' => 'v2',
            'model' => 'gpt-4o-mini',
        ]);
    }

    return $meeting->fresh();
}

test('composes one draft newsletter with both meeting-level summaries', function (): void {
    Mail::fake();
    $meeting = meetingWithMeetingSummaries();
    $newsletter = app(ComposeNewsletter::class)->handle($meeting);

    expect($newsletter->status)->toBe(NewsletterStatus::Draft);
    expect($newsletter->meeting_id)->toBe($meeting->id);
    expect($newsletter->summaries()->count())->toBe(2); // standard + simple
});

test('excludes the plain teaser summary from the newsletter', function (): void {
    Mail::fake();
    $meeting = meetingWithMeetingSummaries();
    $newsletter = app(ComposeNewsletter::class)->handle($meeting);

    $levels = $newsletter->summaries()->get()->map(fn (Summary $s) => $s->level->value)->all();

    expect($levels)->toContain('standard')
        ->and($levels)->toContain('simple')
        ->and($levels)->not->toContain('plain');
});

test('is idempotent — second call updates existing newsletter', function (): void {
    Mail::fake();
    $meeting = meetingWithMeetingSummaries();

    $first = app(ComposeNewsletter::class)->handle($meeting);
    $second = app(ComposeNewsletter::class)->handle($meeting);

    expect($first->id)->toBe($second->id);
    expect(Newsletter::count())->toBe(1);
});

test('standard summary gets position 1 and simple gets position 2', function (): void {
    Mail::fake();
    $meeting = meetingWithMeetingSummaries();
    $newsletter = app(ComposeNewsletter::class)->handle($meeting);

    $positions = $newsletter->summaries()
        ->orderByPivot('position')
        ->get()
        ->mapWithKeys(fn ($s) => [$s->level->value => $s->pivot->position])
        ->all();

    expect($positions['standard'])->toBe(1);
    expect($positions['simple'])->toBe(2);
});

test('sends ReviewReadyMail to admins on first compose', function (): void {
    Mail::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $meeting = meetingWithMeetingSummaries();

    app(ComposeNewsletter::class)->handle($meeting);

    Mail::assertSent(ReviewReadyMail::class, fn ($m) => $m->hasTo($admin->email));
});

test('does not resend ReviewReadyMail on idempotent re-run', function (): void {
    Mail::fake();

    User::factory()->create(['is_admin' => true]);
    $meeting = meetingWithMeetingSummaries();

    app(ComposeNewsletter::class)->handle($meeting); // first run → sends mail
    Mail::assertSentCount(1);

    app(ComposeNewsletter::class)->handle($meeting); // second run → no mail
    Mail::assertSentCount(1); // still 1
});

test('does not send mail when no admins exist', function (): void {
    Mail::fake();

    $meeting = meetingWithMeetingSummaries();
    app(ComposeNewsletter::class)->handle($meeting);

    Mail::assertNothingSent();
});
