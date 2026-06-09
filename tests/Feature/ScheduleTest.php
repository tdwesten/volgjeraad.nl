<?php

use Illuminate\Console\Scheduling\Schedule;

test('the resolve sweep is scheduled every fifteen minutes', function (): void {
    $events = collect(app(Schedule::class)->events());

    $resolve = $events->first(fn ($e) => $e->description === 'volgjeraad:resolve');

    expect($resolve)->not->toBeNull();
    expect($resolve->expression)->toBe('*/15 * * * *');
});

test('the old daily match-videos schedule is gone', function (): void {
    $events = collect(app(Schedule::class)->events());
    expect($events->contains(fn ($e) => $e->description === 'volgjeraad:match-videos'))->toBeFalse();
});
