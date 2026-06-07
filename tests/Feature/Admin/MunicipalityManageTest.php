<?php

use App\Actions\Municipalities\FindMunicipalityStream;
use App\Models\Municipality;
use App\Models\User;
use App\Services\Ori\OriClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function admin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

test('admin can create a municipality', function (): void {
    $this->actingAs(admin())
        ->post('/admin/municipalities', [
            'name' => 'Brummen',
            'slug' => 'brummen',
            'ori_index' => 'ori_brummen',
            'timezone' => 'Europe/Amsterdam',
            'active' => true,
        ])
        ->assertRedirect();

    $municipality = Municipality::where('slug', 'brummen')->first();

    expect($municipality)->not->toBeNull();
    expect($municipality->name)->toBe('Brummen');
    expect($municipality->ori_index)->toBe('ori_brummen');
    expect($municipality->active)->toBeTrue();
    expect($municipality->settings)->toBeNull();
});

test('store persists the youtube channel id into settings when provided', function (): void {
    $this->actingAs(admin())
        ->post('/admin/municipalities', [
            'name' => 'Brummen',
            'slug' => 'brummen',
            'ori_index' => 'ori_brummen',
            'active' => true,
            'youtube_channel_id' => 'UCGc0GMqy0qVntwXlEPTnqaA',
        ])
        ->assertRedirect();

    $municipality = Municipality::where('slug', 'brummen')->firstOrFail();

    expect($municipality->settings['youtube_channel_id'])->toBe('UCGc0GMqy0qVntwXlEPTnqaA');
});

test('store rejects an ori_index with path/wildcard characters', function (): void {
    $this->actingAs(admin())
        ->post('/admin/municipalities', [
            'name' => 'Hack',
            'slug' => 'hack',
            'ori_index' => 'ori_brummen/_all',
            'active' => true,
        ])
        ->assertSessionHasErrors('ori_index');

    expect(Municipality::where('slug', 'hack')->count())->toBe(0);
});

test('store rejects a malformed youtube channel id', function (): void {
    $this->actingAs(admin())
        ->post('/admin/municipalities', [
            'name' => 'Brummen',
            'slug' => 'brummen',
            'ori_index' => 'ori_brummen',
            'active' => true,
            'youtube_channel_id' => 'not-a-channel',
        ])
        ->assertSessionHasErrors('youtube_channel_id');
});

test('validate-ori rejects a malformed ori_index', function (): void {
    // shouldRenderJsonWhen is beperkt tot api/*, dus validatiefouten redirecten met sessie-errors.
    $this->actingAs(admin())
        ->post('/admin/municipalities/validate-ori', ['ori_index' => '*/_all'])
        ->assertSessionHasErrors('ori_index');
});

test('store rejects a duplicate slug', function (): void {
    Municipality::factory()->create(['slug' => 'brummen']);

    $this->actingAs(admin())
        ->post('/admin/municipalities', [
            'name' => 'Brummen',
            'slug' => 'brummen',
            'ori_index' => 'ori_brummen',
            'active' => true,
        ])
        ->assertSessionHasErrors('slug');

    expect(Municipality::where('slug', 'brummen')->count())->toBe(1);
});

test('toggle active flips the boolean', function (): void {
    $municipality = Municipality::factory()->create(['active' => true]);

    $this->actingAs(admin())
        ->patch("/admin/municipalities/{$municipality->id}/active")
        ->assertRedirect();

    expect($municipality->fresh()->active)->toBeFalse();

    $this->actingAs(admin())
        ->patch("/admin/municipalities/{$municipality->id}/active")
        ->assertRedirect();

    expect($municipality->fresh()->active)->toBeTrue();
});

test('validate-ori returns the probe result as json', function (): void {
    $this->mock(OriClient::class, function ($mock): void {
        $mock->shouldReceive('probeIndex')
            ->once()
            ->with('ori_brummen')
            ->andReturn([
                'exists' => true,
                'meeting_count' => 42,
                'latest_meeting' => ['name' => 'Raadsvergadering', 'date' => '2026-05-20T19:30:00'],
                'error' => null,
            ]);
    });

    $this->actingAs(admin())
        ->postJson('/admin/municipalities/validate-ori', ['ori_index' => 'ori_brummen'])
        ->assertOk()
        ->assertJson([
            'exists' => true,
            'meeting_count' => 42,
            'latest_meeting' => ['name' => 'Raadsvergadering', 'date' => '2026-05-20T19:30:00'],
            'error' => null,
        ]);
});

test('find-stream returns the agent result as json', function (): void {
    $this->mock(FindMunicipalityStream::class, function ($mock): void {
        $mock->shouldReceive('handle')
            ->once()
            ->with('Brummen')
            ->andReturn([
                'channel_id' => 'UC123456789',
                'channel_title' => 'Gemeente Brummen',
                'channel_url' => 'https://youtube.com/channel/UC123456789',
                'confidence' => 90,
                'reason' => 'Officieel kanaal gevonden.',
            ]);
    });

    $this->actingAs(admin())
        ->postJson('/admin/municipalities/find-stream', ['name' => 'Brummen'])
        ->assertOk()
        ->assertJson([
            'channel_id' => 'UC123456789',
            'channel_title' => 'Gemeente Brummen',
            'confidence' => 90,
        ]);
});

test('channel endpoint stores the youtube channel id in settings', function (): void {
    $municipality = Municipality::factory()->create(['settings' => null]);

    $this->actingAs(admin())
        ->post("/admin/municipalities/{$municipality->id}/channel", [
            'youtube_channel_id' => 'UCabcdefghijklmnopqrstuv',
        ])
        ->assertRedirect();

    expect($municipality->fresh()->settings['youtube_channel_id'])->toBe('UCabcdefghijklmnopqrstuv');
});

test('channel endpoint clears the channel when empty', function (): void {
    $municipality = Municipality::factory()->create(['settings' => ['youtube_channel_id' => 'UC987654321']]);

    $this->actingAs(admin())
        ->post("/admin/municipalities/{$municipality->id}/channel", [
            'youtube_channel_id' => '',
        ])
        ->assertRedirect();

    expect($municipality->fresh()->settings['youtube_channel_id'] ?? null)->toBeNull();
});

test('non-admin gets 403 on all management routes', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $municipality = Municipality::factory()->create();

    $this->actingAs($user)->get('/admin/municipalities/create')->assertForbidden();
    $this->actingAs($user)->post('/admin/municipalities', [])->assertForbidden();
    $this->actingAs($user)->postJson('/admin/municipalities/validate-ori', ['ori_index' => 'x'])->assertForbidden();
    $this->actingAs($user)->postJson('/admin/municipalities/find-stream', ['name' => 'x'])->assertForbidden();
    $this->actingAs($user)->patch("/admin/municipalities/{$municipality->id}/active")->assertForbidden();
    $this->actingAs($user)->post("/admin/municipalities/{$municipality->id}/channel", [])->assertForbidden();
});

test('admin can view the create page', function (): void {
    $this->withoutVite()
        ->actingAs(admin())
        ->get('/admin/municipalities/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/Municipalities/Create'));
});
