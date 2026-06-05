<?php

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sets the youtube channel id for an existing municipality', function (): void {
    $municipality = Municipality::factory()->create(['slug' => 'brummen', 'settings' => []]);

    $this->artisan('volgjeraad:set-youtube-channel brummen UCabcdefg1234')
        ->assertSuccessful()
        ->expectsOutputToContain('UCabcdefg1234');

    expect($municipality->fresh()->settings['youtube_channel_id'])->toBe('UCabcdefg1234');
});

test('overwrites an existing channel id', function (): void {
    $municipality = Municipality::factory()->create([
        'slug' => 'brummen',
        'settings' => ['youtube_channel_id' => 'UCold'],
    ]);

    $this->artisan('volgjeraad:set-youtube-channel brummen UCnew123456')
        ->assertSuccessful();

    expect($municipality->fresh()->settings['youtube_channel_id'])->toBe('UCnew123456');
});

test('preserves other settings keys when setting the channel id', function (): void {
    $municipality = Municipality::factory()->create([
        'slug' => 'brummen',
        'settings' => ['some_other_key' => 'value'],
    ]);

    $this->artisan('volgjeraad:set-youtube-channel brummen UCtest1234')
        ->assertSuccessful();

    $settings = $municipality->fresh()->settings;
    expect($settings['youtube_channel_id'])->toBe('UCtest1234');
    expect($settings['some_other_key'])->toBe('value');
});

test('returns failure when municipality slug is unknown', function (): void {
    $this->artisan('volgjeraad:set-youtube-channel nonexistent UCtest1234')
        ->assertFailed()
        ->expectsOutputToContain('not found');
});
