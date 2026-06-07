<?php

use App\Models\MunicipalityRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('non-admin gets 403 on municipality requests index', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin/gemeente-aanvragen')->assertForbidden();
});

test('admin sees municipality requests index', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    MunicipalityRequest::factory()->count(3)->create();

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin/gemeente-aanvragen')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/MunicipalityRequests/Index')
            ->has('requests.data', 3)
        );
});
