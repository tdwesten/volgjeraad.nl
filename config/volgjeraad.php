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
        'default_summary_model' => env('AI_SUMMARY_MODEL', 'gpt-5.4-mini'),
        'default_eval_model' => env('AI_EVAL_MODEL', 'gpt-5.4-mini'),
        'prompt_version' => 'v2',
        'cost_cap_cents_per_meeting' => 100,
        'confidence_highlight_threshold' => 60,
        // Max chars of agenda+PDF source text passed to the AI (≈24 000 chars ≈ 6000 tokens).
        // Verhoogd t.o.v. v1 omdat we nu alle stukken als één geheel meesturen.
        'max_source_chars' => 80000,
        // Apart tekenbudget voor het transcript-blok zodat het transcript nooit
        // volledig wegvalt achter een lange agenda/PDF-bron (≈15000 tokens).
        'max_transcript_chars' => 60000,
    ],

    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
        'base_url' => env('YOUTUBE_BASE_URL', 'https://www.googleapis.com/youtube/v3'),
        'timeout' => 20,
        'connect_timeout' => 5,
        // Zoekvenster rond meeting->starts_at (dagen vóór en ná).
        'search_window_days' => 3,
        // Minimale agent-confidence (0-100) om automatisch te koppelen.
        'match_confidence_threshold' => 75,
        // Stop met zoeken voor meetings ouder dan N dagen (geldt op NotFound/geen-video).
        'max_find_days' => 14,
        // Maximaal aantal transcript-fetch-pogingen per video voordat we opgeven.
        'max_transcript_attempts' => 4,
        // Hoe lang we met de vergadering-samenvatting wachten op een transcript voordat
        // we 'm zonder transcript maken (apart van max_find_days). 'Wachten vóór review'.
        'transcript_wait_days' => 7,
    ],

    'transcript' => [
        'supadata' => [
            'api_key' => env('SUPADATA_API_KEY'),
            'base_url' => env('SUPADATA_BASE_URL', 'https://api.supadata.ai/v1'),
            // Universal-endpoint mode: native|auto|generate. 'auto' = captions, val terug op AI.
            'mode' => env('SUPADATA_MODE', 'auto'),
            'timeout' => 60,
            'connect_timeout' => 5,
            // Async (202 + jobId) job-polling.
            'poll_max_attempts' => 10,
            'poll_interval_ms' => 2000,
        ],
    ],

    'launch_date' => env('VOLGJERAAD_LAUNCH_DATE'),

    'backfill_recent_meetings' => 2,

    /*
     * Cost in cents per 1M tokens: [input_cents, output_cents].
     * Prices verified 2026-06-05 (OpenAI pricing page; fallback gpt-4o-mini).
     * gpt-4o-mini:  $0.15/1M input, $0.60/1M output  → 15 / 60 cents
     */
    'model_prices' => [
        // gpt-5.4-mini: schatting — verifieer tegen de actuele OpenAI-prijspagina.
        'gpt-5.4-mini' => [25, 200],
        'gpt-4o-mini' => [15, 60],
        'gpt-4o' => [250, 1000],
    ],
];
