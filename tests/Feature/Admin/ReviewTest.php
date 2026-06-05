<?php

use App\Enums\NewsletterStatus;
use App\Enums\SummaryStatus;
use App\Jobs\SendNewsletterJob;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Newsletter;
use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('unauthenticated user is redirected to login on admin routes', function (): void {
    $this->get('/admin')->assertRedirect('/login');
});

test('non-admin user gets 403 on admin routes', function (): void {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('admin user can access dashboard', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/Dashboard'));
});

test('review index renders draft newsletters', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    Newsletter::factory()->create([
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => NewsletterStatus::Draft->value,
    ]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Review/Index')
            ->has('newsletters', 1)
        );
});

test('review index highlights low confidence summaries', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    $newsletter = Newsletter::factory()->create([
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => NewsletterStatus::Draft->value,
    ]);

    $summary = Summary::factory()->create([
        'meeting_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'confidence' => 45,
        'status' => SummaryStatus::Draft->value,
    ]);
    $newsletter->summaries()->attach($summary->id, ['position' => 1]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/Review/Index')
            ->where('newsletters.0.low_confidence', true)
        );
});

test('review index does not highlight high confidence summaries', function (): void {
    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);
    $newsletter = Newsletter::factory()->create([
        'municipality_id' => $municipality->id,
        'meeting_id' => $meeting->id,
        'status' => NewsletterStatus::Draft->value,
    ]);

    $summary = Summary::factory()->create([
        'meeting_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'confidence' => 85,
        'status' => SummaryStatus::Draft->value,
    ]);
    $newsletter->summaries()->attach($summary->id, ['position' => 1]);

    $this->withoutVite()
        ->actingAs($admin)
        ->get('/admin/review')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('newsletters.0.low_confidence', false)
        );
});

test('approve publishes summaries and dispatches SendNewsletterJob', function (): void {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    $summary = Summary::factory()->create([
        'meeting_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
    ]);

    $this->actingAs($admin)
        ->post("/admin/review/{$meeting->id}/approve")
        ->assertRedirect('/admin/review');

    expect($summary->fresh()->status)->toBe(SummaryStatus::Published);
    Queue::assertPushed(SendNewsletterJob::class);
});

test('approve creates newsletter with approved status', function (): void {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $municipality = Municipality::factory()->create();
    $meeting = Meeting::factory()->create(['municipality_id' => $municipality->id]);

    Summary::factory()->create([
        'meeting_id' => $meeting->id,
        'municipality_id' => $municipality->id,
        'summarizable_type' => Meeting::class,
        'summarizable_id' => $meeting->id,
        'status' => SummaryStatus::Draft->value,
    ]);

    $this->actingAs($admin)->post("/admin/review/{$meeting->id}/approve");

    $newsletter = Newsletter::where('meeting_id', $meeting->id)->first();
    expect($newsletter)->not->toBeNull();
    expect($newsletter->status)->toBe(NewsletterStatus::Approved);
});
