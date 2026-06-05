<?php

use App\Models\EvaluationCase;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds the Brummen municipality', function () {
    $this->seed();

    $brummen = Municipality::where('slug', 'brummen')->first();

    expect($brummen)->not->toBeNull()
        ->and($brummen->ori_index)->toBe('ori_brummen')
        ->and($brummen->raad_pattern)->toBe('raadsvergadering')
        ->and($brummen->active)->toBeTrue();
});

it('seeds exactly one admin user with admin rights', function () {
    $this->seed();

    $admins = User::where('is_admin', true)->get();

    expect($admins)->toHaveCount(1)
        ->and($admins->first()->email)->not->toBe('')
        ->and(Hash::check('password', $admins->first()->password))->toBeTrue();
});

it('seeds evaluation cases for Brummen', function () {
    $this->seed();

    expect(EvaluationCase::count())->toBeGreaterThanOrEqual(5);
});
