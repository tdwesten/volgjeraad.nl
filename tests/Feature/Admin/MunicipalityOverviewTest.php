<?php

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Subscriber;
use App\Models\Summary;
use App\Models\User;
use App\Services\Ori\OriClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // The municipality show page probes ORI; keep tests network-free.
    $this->mock(OriClient::class, function ($mock): void {
        $mock->shouldReceive('probeIndex')->andReturn([
            'exists' => true,
            'meeting_count' => 0,
            'latest_meeting' => null,
            'error' => null,
        ]);
    });
});

test('non-admin gets 403 on municipalities index', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/municipalities')->assertForbidden();
});

test('non-admin gets 403 on municipalities show', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $municipality = Municipality::factory()->create();

    $this->actingAs($user)->get("/admin/municipalities/{$municipality->id}")->assertForbidden();
});

test('admin sees municipalities index with correct counts', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    // Use a name that sorts first alphabetically so index 0 is predictable
    $municipality = Municipality::factory()->create(['name' => 'AAA Testgemeente']);

    Meeting::factory()->count(3)->create(['municipality_id' => $municipality->id]);

    Subscriber::factory()->create([
        'municipality_id' => $municipality->id,
        'confirmed_at' => now(),
    ]);
    Subscriber::factory()->create([
        'municipality_id' => $municipality->id,
        'confirmed_at' => null,
    ]);

    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    // Create summary directly to avoid SummaryFactory's internal Meeting::factory() side-effects
    Summary::factory()->state(['status' => SummaryStatus::Published])->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin/municipalities')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Municipalities/Index')
            ->where('municipalities.0.name', 'AAA Testgemeente')
            ->where('municipalities.0.meetings_count', 4)
            ->where('municipalities.0.confirmed_subscribers_count', 1)
            ->where('municipalities.0.published_summaries_count', 1)
        );
});

test('admin sees municipalities show with meetings', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();

    Meeting::factory()->count(2)->create(['municipality_id' => $municipality->id]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/municipalities/{$municipality->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Municipalities/Show')
            ->has('meetings', 2)
            ->where('municipality.id', $municipality->id)
        );
});

test('show returns correct summary status for published summary', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->published()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/municipalities/{$municipality->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings.0.summary_status', 'Gepubliceerd')
        );
});

test('show returns correct summary status for draft summary', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/municipalities/{$municipality->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings.0.summary_status', 'Concept')
        );
});

test('show exposes the plain teaser per meeting', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);
    Summary::factory()->published()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'level' => SummaryLevel::Plain->value,
        'body' => 'Korte teaser over deze vergadering.',
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/municipalities/{$municipality->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings.0.teaser', 'Korte teaser over deze vergadering.')
        );
});

test('show returns geen for non-council meeting without summaries', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'type' => MeetingType::Committee->value,
        'ingest_mode' => IngestMode::Summarize->value,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get("/admin/municipalities/{$municipality->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('meetings.0.summary_status', 'Geen')
        );
});
