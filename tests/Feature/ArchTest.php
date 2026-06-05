<?php

use Illuminate\Contracts\Queue\ShouldQueue;

arch('debug helpers are never committed')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('jobs are queueable')
    ->expect('App\Jobs')
    ->toImplement(ShouldQueue::class);

arch('domain actions expose a handle method')
    ->expect([
        'App\Actions\Ingest',
        'App\Actions\Summaries',
        'App\Actions\Ai',
        'App\Actions\Newsletters',
        'App\Actions\Subscriptions',
    ])
    ->toHaveMethod('handle');

arch('controllers are suffixed')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('enums are string backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();
