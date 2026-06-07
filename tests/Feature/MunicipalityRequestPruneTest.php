<?php

use App\Models\MunicipalityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('oude gemeente-aanvragen worden gepruned en recente blijven', function (): void {
    $old = MunicipalityRequest::factory()->create([
        'created_at' => now()->subMonths(4),
    ]);
    $recent = MunicipalityRequest::factory()->create([
        'created_at' => now(),
    ]);

    $this->artisan('model:prune', ['--model' => [MunicipalityRequest::class]]);

    expect(MunicipalityRequest::find($old->id))->toBeNull();
    expect(MunicipalityRequest::find($recent->id))->not->toBeNull();
});
