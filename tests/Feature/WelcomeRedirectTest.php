<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('homepage renders inertia landing component', function (): void {
    $this->withoutVite()
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page->component('Landing'));
});

test('homepage returns 200 and uses inertia app view', function (): void {
    $this->withoutVite()
        ->get('/')
        ->assertStatus(200)
        ->assertViewIs('app');
});
