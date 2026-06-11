<?php

use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('public pages expose dynamic page titles', function (): void {
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create([
        'municipality_id' => $municipality->id,
        'name' => 'Raadsvergadering juni',
    ]);
    $newsletter = Newsletter::factory()->create([
        'municipality_id' => $municipality->id,
        'subject' => 'Nieuwsbrief juni',
    ]);

    $this->withoutVite()
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->where('pageTitle', null)
        );

    $this->withoutVite()
        ->get("/{$municipality->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Municipality/Show')
            ->where('pageTitle', $municipality->name)
        );

    $this->withoutVite()
        ->get("/{$municipality->slug}/archief")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Municipality/Archive')
            ->where('pageTitle', "Archief {$municipality->name}")
        );

    $this->withoutVite()
        ->get("/{$municipality->slug}/vergadering/{$meeting->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Meeting/Show')
            ->where('pageTitle', "{$meeting->name} - {$municipality->name}")
        );

    $this->withoutVite()
        ->get("/nieuwsbrief/{$newsletter->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Newsletter/Web')
            ->where('pageTitle', $newsletter->subject)
        );
});
