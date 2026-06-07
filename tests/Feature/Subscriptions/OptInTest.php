<?php

use App\Mail\ConfirmSubscriptionMail;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Models\Municipality;
use App\Models\Subscriber;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    Mail::fake();
});

test('subscriber can register and confirmation mail is sent', function (): void {
    $municipality = Municipality::factory()->create();

    $this->post('/aanmelden', [
        'email' => 'user@example.com',
        'municipality_slug' => $municipality->slug,
        'level' => 'standard',
    ])->assertRedirect();

    $subscriber = Subscriber::where('email', 'user@example.com')
        ->where('municipality_id', $municipality->id)
        ->first();

    expect($subscriber)->not->toBeNull()
        ->and($subscriber->confirmed_at)->toBeNull();

    Mail::assertSent(ConfirmSubscriptionMail::class, fn ($mail) => $mail->hasTo('user@example.com'));
});

test('confirm sets confirmed_at', function (): void {
    $municipality = Municipality::factory()->create();
    $subscriber = Subscriber::factory()->unconfirmed()->create(['municipality_id' => $municipality->id]);

    $this->get("/bevestig/{$subscriber->confirmation_token}")
        ->assertRedirect("/{$municipality->slug}");

    expect($subscriber->fresh()->confirmed_at)->not->toBeNull();
});

test('unsubscribe sets unsubscribed_at', function (): void {
    $municipality = Municipality::factory()->create();
    $subscriber = Subscriber::factory()->confirmed()->create(['municipality_id' => $municipality->id]);

    $this->get("/uitschrijven/{$subscriber->unsubscribe_token}")
        ->assertRedirect("/{$municipality->slug}");

    expect($subscriber->fresh()->unsubscribed_at)->not->toBeNull();
});

test('duplicate registration does not create a second subscriber', function (): void {
    $municipality = Municipality::factory()->create();

    $this->post('/aanmelden', [
        'email' => 'user@example.com',
        'municipality_slug' => $municipality->slug,
        'level' => 'standard',
    ]);

    $this->post('/aanmelden', [
        'email' => 'user@example.com',
        'municipality_slug' => $municipality->slug,
        'level' => 'standard',
    ]);

    expect(
        Subscriber::where('email', 'user@example.com')
            ->where('municipality_id', $municipality->id)
            ->count()
    )->toBe(1);
});

test('invalid email is rejected', function (): void {
    $municipality = Municipality::factory()->create();

    $this->post('/aanmelden', [
        'email' => 'not-an-email',
        'municipality_slug' => $municipality->slug,
        'level' => 'standard',
    ])->assertSessionHasErrors('email');
});

test('invalid municipality slug is rejected', function (): void {
    $this->post('/aanmelden', [
        'email' => 'user@example.com',
        'municipality_slug' => 'nonexistent-slug',
        'level' => 'standard',
    ])->assertSessionHasErrors('municipality_slug');
});

test('te veel aanmeldingen worden ge-throttled', function (): void {
    $municipality = Municipality::factory()->create();

    collect(range(1, 6))->each(function () use ($municipality): void {
        $this->post('/aanmelden', [
            'email' => 'user@example.com',
            'municipality_slug' => $municipality->slug,
            'level' => 'standard',
        ])->assertRedirect();
    });

    $this->post('/aanmelden', [
        'email' => 'user@example.com',
        'municipality_slug' => $municipality->slug,
        'level' => 'standard',
    ])->assertStatus(429);
});

test('invalid level is rejected', function (): void {
    $municipality = Municipality::factory()->create();

    $this->post('/aanmelden', [
        'email' => 'user@example.com',
        'municipality_slug' => $municipality->slug,
        'level' => 'premium',
    ])->assertSessionHasErrors('level');
});
