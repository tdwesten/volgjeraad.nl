<?php

return [
    'dsn' => env('SENTRY_DSN'),

    // Stuur applicatie-logs (via het sentry_logs channel) door naar Sentry Logs.
    'enable_logs' => true,

    'release' => env('SENTRY_RELEASE'),

    'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV')),

    'breadcrumbs' => [
        'logs' => true,
        'sql_queries' => true,
        'sql_bindings' => true,
        'queue_info' => true,
        'command_info' => true,
    ],

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.1),
];
