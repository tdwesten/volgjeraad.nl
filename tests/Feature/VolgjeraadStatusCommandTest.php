<?php

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('shows status for all municipalities by default', function (): void {
    Municipality::factory()->create(['slug' => 'brummen', 'name' => 'Gemeente Brummen']);

    $this->artisan('volgjeraad:status')
        ->assertSuccessful()
        ->expectsOutputToContain('Queue')
        ->expectsOutputToContain('Gemeente Brummen');
});

test('shows status for a single municipality by slug', function (): void {
    Municipality::factory()->create(['slug' => 'brummen', 'name' => 'Gemeente Brummen']);
    Municipality::factory()->create(['slug' => 'apeldoorn', 'name' => 'Gemeente Apeldoorn']);

    $this->artisan('volgjeraad:status brummen')
        ->assertSuccessful()
        ->expectsOutputToContain('Gemeente Brummen')
        ->doesntExpectOutputToContain('Gemeente Apeldoorn');
});

test('returns failure for an unknown municipality slug', function (): void {
    $this->artisan('volgjeraad:status nonexistent')
        ->assertFailed()
        ->expectsOutputToContain('Geen gemeente gevonden');
});
