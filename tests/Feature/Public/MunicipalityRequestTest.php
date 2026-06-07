<?php

use App\Mail\MunicipalityRequestedMail;
use App\Models\MunicipalityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Mail::fake();
});

test('een gemeente-aanvraag stuurt een mail naar de admins', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['is_admin' => false]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Apeldoorn',
        'email' => 'inwoner@example.com',
    ])->assertRedirect();

    Mail::assertQueued(MunicipalityRequestedMail::class, fn (MunicipalityRequestedMail $mail) => $mail->hasTo($admin->email)
        && $mail->municipalityName === 'Apeldoorn'
        && $mail->requesterEmail === 'inwoner@example.com'
    );
    Mail::assertQueuedCount(1);
});

test('elke admin krijgt exact één mail die alleen aan henzelf is geadresseerd', function (): void {
    $first = User::factory()->create(['is_admin' => true]);
    $second = User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Apeldoorn',
        'email' => 'inwoner@example.com',
    ])->assertRedirect();

    Mail::assertQueuedCount(2);

    Mail::assertQueued(MunicipalityRequestedMail::class, fn (MunicipalityRequestedMail $mail) => $mail->hasTo($first->email)
        && ! $mail->hasTo($second->email)
    );
    Mail::assertQueued(MunicipalityRequestedMail::class, fn (MunicipalityRequestedMail $mail) => $mail->hasTo($second->email)
        && ! $mail->hasTo($first->email)
    );
});

test('e-mailadres is optioneel', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Zutphen',
    ])->assertRedirect();

    Mail::assertQueued(MunicipalityRequestedMail::class, fn (MunicipalityRequestedMail $mail) => $mail->municipalityName === 'Zutphen'
        && $mail->requesterEmail === null
    );
});

test('gemeentenaam is verplicht', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'email' => 'inwoner@example.com',
    ])->assertSessionHasErrors('municipality');

    Mail::assertNothingQueued();
});

test('ongeldig e-mailadres wordt geweigerd', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Deventer',
        'email' => 'geen-email',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingQueued();
});

test('honeypot wordt stil genegeerd', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Apeldoorn',
        'email' => 'inwoner@example.com',
        'website' => 'spam',
    ])->assertRedirect();

    Mail::assertNothingQueued();
    expect(MunicipalityRequest::count())->toBe(0);
});

test('een aanvraag wordt opgeslagen met de gesaneerde naam', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => '  Apeldoorn  ',
        'email' => 'inwoner@example.com',
    ])->assertRedirect();

    expect(MunicipalityRequest::count())->toBe(1);

    $request = MunicipalityRequest::first();
    expect($request->municipality)->toBe('Apeldoorn');
    expect($request->email)->toBe('inwoner@example.com');
});

test('control chars en newlines in de gemeentenaam worden gestript', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => "Apel\ndoorn",
    ])->assertRedirect();

    $request = MunicipalityRequest::first();
    expect($request->municipality)->toBe('Apel doorn');
    expect($request->municipality)->not->toContain("\n");
});

test('te veel aanvragen worden ge-throttled', function (): void {
    User::factory()->create(['is_admin' => true]);

    collect(range(1, 6))->each(function (): void {
        $this->post('/gemeente-aanvragen', [
            'municipality' => 'Apeldoorn',
        ])->assertRedirect();
    });

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Apeldoorn',
    ])->assertStatus(429);
});
