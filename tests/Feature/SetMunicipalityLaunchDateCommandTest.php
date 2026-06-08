<?php

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sets the launch date for an existing municipality', function (): void {
    $municipality = Municipality::factory()->create(['slug' => 'brummen', 'launch_date' => null]);

    $this->artisan('volgjeraad:set-launch-date brummen 2026-06-08')
        ->assertSuccessful()
        ->expectsOutputToContain('2026-06-08');

    expect($municipality->fresh()->launch_date->toDateString())->toBe('2026-06-08');
});

test('returns failure for an unknown municipality slug', function (): void {
    $this->artisan('volgjeraad:set-launch-date nonexistent 2026-06-08')
        ->assertFailed()
        ->expectsOutputToContain('not found');
});

test('returns failure for an invalid date', function (): void {
    Municipality::factory()->create(['slug' => 'brummen']);

    $this->artisan('volgjeraad:set-launch-date brummen not-a-date')
        ->assertFailed()
        ->expectsOutputToContain('Invalid date');
});
