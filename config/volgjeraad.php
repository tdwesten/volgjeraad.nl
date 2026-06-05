<?php

return [
    'ori' => [
        'base_url' => env('ORI_BASE_URL', 'https://api.openraadsinformatie.nl/v1/elastic'),
        'timeout' => 20,
        'connect_timeout' => 5,
        'batch_size' => 100,
        'window_past_days' => 14,
        'window_future_days' => 60,
    ],

    'ai' => [
        'default_summary_model' => env('AI_SUMMARY_MODEL', 'gpt-4o-mini'),
        'default_eval_model' => env('AI_EVAL_MODEL', 'gpt-4o-mini'),
        'prompt_version' => 'v1',
        'cost_cap_cents_per_meeting' => 100,
        'confidence_highlight_threshold' => 60,
        // Max chars of source text passed to the AI (≈6000 tokens at 4 chars/token)
        'max_source_chars' => 24000,
    ],

    'launch_date' => env('VOLGJERAAD_LAUNCH_DATE'),

    'backfill_recent_meetings' => 2,

    /*
     * Cost in cents per 1M tokens: [input_cents, output_cents].
     * Prices verified 2026-06-05 (OpenAI pricing page; fallback gpt-4o-mini).
     * gpt-4o-mini:  $0.15/1M input, $0.60/1M output  → 15 / 60 cents
     */
    'model_prices' => [
        'gpt-4o-mini' => [15, 60],
        'gpt-4o' => [250, 1000],
    ],
];
