<?php

use App\Mail\MunicipalityRequestedMail;
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

    Mail::assertSent(MunicipalityRequestedMail::class, fn (MunicipalityRequestedMail $mail) => $mail->hasTo($admin->email)
        && $mail->municipalityName === 'Apeldoorn'
        && $mail->requesterEmail === 'inwoner@example.com'
    );
    Mail::assertSentCount(1);
});

test('e-mailadres is optioneel', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Zutphen',
    ])->assertRedirect();

    Mail::assertSent(MunicipalityRequestedMail::class, fn (MunicipalityRequestedMail $mail) => $mail->municipalityName === 'Zutphen'
        && $mail->requesterEmail === null
    );
});

test('gemeentenaam is verplicht', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'email' => 'inwoner@example.com',
    ])->assertSessionHasErrors('municipality');

    Mail::assertNothingSent();
});

test('ongeldig e-mailadres wordt geweigerd', function (): void {
    User::factory()->create(['is_admin' => true]);

    $this->post('/gemeente-aanvragen', [
        'municipality' => 'Deventer',
        'email' => 'geen-email',
    ])->assertSessionHasErrors('email');

    Mail::assertNothingSent();
});
