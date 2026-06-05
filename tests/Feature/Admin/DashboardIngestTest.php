<?php

use App\Jobs\IngestMunicipalityMeetingsJob;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches an ingest job per active municipality for an admin', function () {
    Queue::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $active = Municipality::factory()->create(['active' => true]);
    Municipality::factory()->create(['active' => false]);

    $this->actingAs($admin)
        ->post(route('admin.ingest'))
        ->assertRedirect()
        ->assertSessionHas('success');

    Queue::assertPushed(IngestMunicipalityMeetingsJob::class, 1);
    Queue::assertPushed(
        fn (IngestMunicipalityMeetingsJob $job) => $job->municipalityId === $active->id,
    );
});

it('forbids non-admins from triggering an ingest', function () {
    Queue::fake();

    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->post(route('admin.ingest'))
        ->assertForbidden();

    Queue::assertNothingPushed();
});
