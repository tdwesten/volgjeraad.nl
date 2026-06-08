<?php

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the homepage injects an og:image meta tag and template', function (): void {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('property="og:image"', false);
    $response->assertSee('/og-image/', false);
    $response->assertSee('Raadsvergaderingen, automatisch samengevat.', false);
});

test('a municipality page injects an og:image with the municipality name', function (): void {
    Municipality::factory()->create(['slug' => 'brummen', 'name' => 'Gemeente Brummen', 'active' => true]);

    $response = $this->get('/brummen');

    $response->assertOk();
    $response->assertSee('property="og:image"', false);
    $response->assertSee('name="twitter:card"', false);
    $response->assertSee('Gemeente Brummen', false);
});
