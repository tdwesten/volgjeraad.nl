<?php

use App\Enums\SummaryLevel;
use App\Enums\SummaryStatus;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Summary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('landing toont actieve gemeenten en een uitgelichte vergadering', function (): void {
    $municipality = Municipality::factory()->create(['active' => true, 'name' => 'Brummen']);
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'starts_at' => now()->subDay(),
    ]);
    // Direct aanmaken om de Meeting/Municipality-side-effects van SummaryFactory te vermijden.
    Summary::create([
        'summarizable_type' => $meeting->getMorphClass(),
        'summarizable_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'level' => SummaryLevel::Plain->value,
        'language' => 'nl',
        'source_hash' => 'hash-plain',
        'status' => SummaryStatus::Published->value,
        'title' => '',
        'body' => 'Korte teaser voor de voorpagina.',
        'input_tokens' => 0,
        'output_tokens' => 0,
        'prompt_version' => 'v2',
        'model' => 'gpt-4o-mini',
    ]);

    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->has('municipalities', 1)
            ->where('municipalities.0.name', 'Brummen')
            ->where('featuredMeeting.id', $meeting->id)
            ->where('featuredMeeting.municipality.slug', $municipality->slug)
            ->where('featuredMeeting.teaser', 'Korte teaser voor de voorpagina.')
        );
});

test('featuredMeeting is null zonder gepubliceerde samenvatting', function (): void {
    Municipality::factory()->create(['active' => true]);

    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->where('featuredMeeting', null)
        );
});

test('inactieve gemeenten staan niet in de lijst', function (): void {
    Municipality::factory()->create(['active' => false, 'name' => 'Verborgen']);

    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->has('municipalities', 0)
        );
});
