<?php

use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('admin can view subscribers list', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    Subscriber::factory()->confirmed()->count(3)->create();

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin/subscribers')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Subscribers/Index')
            ->has('subscribers.data', 3)
        );
});

test('admin can delete a subscriber (AVG hard delete)', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $subscriber = Subscriber::factory()->confirmed()->create();

    $this->actingAs($admin)
        ->delete("/admin/subscribers/{$subscriber->id}")
        ->assertRedirect();

    expect(Subscriber::find($subscriber->id))->toBeNull();
});

test('admin can export subscribers as CSV', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $subscriber = Subscriber::factory()->confirmed()->create();

    $response = $this->actingAs($admin)->get('/admin/subscribers/export');

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->streamedContent())->toContain($subscriber->email);
});

test('non-admin cannot delete subscribers', function (): void {
    $user = User::factory()->create(['is_admin' => false]);
    $subscriber = Subscriber::factory()->confirmed()->create();

    $this->actingAs($user)
        ->delete("/admin/subscribers/{$subscriber->id}")
        ->assertForbidden();

    expect(Subscriber::find($subscriber->id))->not->toBeNull();
});
