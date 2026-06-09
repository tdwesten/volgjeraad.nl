<?php

use App\Enums\SummaryStatus;
use App\Enums\VideoStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
use App\Models\Meeting;
use App\Models\MeetingVideo;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\Summary;
use Inertia\Testing\AssertableInertia as Assert;

test('landing page renders with municipalities', function (): void {
    $municipality = Municipality::factory()->create(['active' => true]);

    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->has('municipalities', 1)
            ->where('municipalities.0.slug', $municipality->slug)
        );
});

test('municipality show page renders with published summaries', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);
    Summary::factory()->published()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    $this->withoutVite()
        ->get("/{$municipality->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Municipality/Show')
            ->where('municipality.slug', $municipality->slug)
            ->has('meetings', 1)
            ->where('meetings.0.summaries.0.id', fn ($value) => $value !== null)
        );
});

test('draft summaries do not leak on municipality show page', function (): void {
    $municipality = Municipality::factory()->create();
    // Expliciete starts_at in het verleden zodat de meeting deterministisch in de
    // (sinds Taak 15 ook 'plaatsgevonden') publieke lijst valt.
    $meeting = Meeting::factory()->summarizable()->create([
        'municipality_id' => $municipality->id,
        'starts_at' => now()->subDays(5),
    ]);
    Summary::factory()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
        'title' => 'LEKTITEL-CONCEPT',
        'body' => 'LEKINHOUD-CONCEPT',
    ]);

    $response = $this->withoutVite()->get("/{$municipality->slug}")->assertOk();

    // Nieuw gedrag: de plaatsgevonden meeting MAG in de lijst staan, maar met een
    // statusregel i.p.v. de concept-samenvatting. De concept-INHOUD mag niet lekken.
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Municipality/Show')
        ->has('meetings', 1)
        ->where('meetings.0.summaries', [])
        ->where('meetings.0.status_message', fn ($value) => is_string($value) && $value !== '')
    );

    // Harde anti-leak-garantie: noch de concept-titel noch -inhoud zit in de respons.
    expect($response->getContent())->not->toContain('LEKTITEL-CONCEPT');
    expect($response->getContent())->not->toContain('LEKINHOUD-CONCEPT');
});

test('municipality archive page renders metadata-only meetings', function (): void {
    $municipality = Municipality::factory()->create();
    Meeting::factory()->count(3)->create(['municipality_id' => $municipality->id]);

    $this->withoutVite()
        ->get("/{$municipality->slug}/archief")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Municipality/Archive')
            ->where('municipality.slug', $municipality->slug)
            ->has('meetings', 3)
        );
});

test('meeting show page renders both summary levels', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);
    Summary::factory()->published()->standard()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);
    Summary::factory()->published()->simple()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
    ]);

    $this->withoutVite()
        ->get("/{$municipality->slug}/vergadering/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Meeting/Show')
            ->where('meeting.id', $meeting->id)
            ->whereNot('meeting.standard_summary', null)
            ->whereNot('meeting.simple_summary', null)
        );
});

test('draft summaries do not leak on meeting show page', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);
    Summary::factory()->create([
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
    ]);

    $this->withoutVite()
        ->get("/{$municipality->slug}/vergadering/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Meeting/Show')
            ->where('meeting.standard_summary', null)
            ->where('meeting.simple_summary', null)
        );
});

test('newsletter web page renders', function (): void {
    $municipality = Municipality::factory()->create();
    $newsletter = Newsletter::factory()->create(['municipality_id' => $municipality->id]);

    $this->withoutVite()
        ->get("/nieuwsbrief/{$newsletter->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Newsletter/Web')
            ->where('newsletter.id', $newsletter->id)
        );
});

test('meeting show page includes video prop when matched video present', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Matched->value,
        'youtube_video_id' => 'dQw4w9WgXcQ',
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ]);

    $this->withoutVite()
        ->get("/{$municipality->slug}/vergadering/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Meeting/Show')
            ->where('video.youtube_video_id', 'dQw4w9WgXcQ')
            ->where('video.video_url', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ')
        );
});

test('meeting show page video prop is null without matching video', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);

    $this->withoutVite()
        ->get("/{$municipality->slug}/vergadering/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Meeting/Show')
            ->where('video', null)
        );
});

test('meeting show page video prop is null for pending video', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->summarizable()->create(['municipality_id' => $municipality->id]);
    MeetingVideo::factory()->create([
        'meeting_id' => $meeting->id,
        'status' => VideoStatus::Pending->value,
        'youtube_video_id' => 'dQw4w9WgXcQ',
    ]);

    $this->withoutVite()
        ->get("/{$municipality->slug}/vergadering/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Meeting/Show')
            ->where('video', null)
        );
});

test('meeting returns 404 if not belonging to municipality', function (): void {
    $municipalityA = Municipality::factory()->create();
    $municipalityB = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipalityA->id]);

    $this->withoutVite()
        ->get("/{$municipalityB->slug}/vergadering/{$meeting->id}")
        ->assertNotFound();
});
