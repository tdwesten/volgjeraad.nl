<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('migrate fresh runs without errors', function (): void {
    expect(Schema::hasTable('cache'))->toBeTrue();
    expect(Schema::hasTable('cache_locks'))->toBeTrue();
    expect(Schema::hasTable('municipalities'))->toBeTrue();
    expect(Schema::hasTable('meetings'))->toBeTrue();
    expect(Schema::hasTable('agenda_items'))->toBeTrue();
    expect(Schema::hasTable('media_objects'))->toBeTrue();
    expect(Schema::hasTable('summaries'))->toBeTrue();
    expect(Schema::hasTable('subscribers'))->toBeTrue();
    expect(Schema::hasTable('newsletters'))->toBeTrue();
    expect(Schema::hasTable('newsletter_summary'))->toBeTrue();
    expect(Schema::hasTable('ai_usage_records'))->toBeTrue();
    expect(Schema::hasTable('evaluation_cases'))->toBeTrue();
    expect(Schema::hasTable('evaluation_runs'))->toBeTrue();
});

test('municipalities table has expected columns', function (): void {
    expect(Schema::hasColumns('municipalities', [
        'id', 'slug', 'name', 'ori_index', 'timezone', 'active', 'launch_date',
        'backfill_recent_meetings', 'ai_model_summary', 'ai_model_eval',
        'raad_pattern', 'sender_name', 'settings',
    ]))->toBeTrue();
});

test('meetings table has expected columns', function (): void {
    expect(Schema::hasColumns('meetings', [
        'id', 'municipality_id', 'ori_id', 'type', 'committee_ori_id', 'committee_name',
        'name', 'starts_at', 'status', 'source_url', 'raw_payload', 'raw_payload_hash',
        'ingest_mode', 'last_seen_at', 'agenda_ingested_at', 'summarized_at',
    ]))->toBeTrue();
});

test('summaries table has expected columns', function (): void {
    expect(Schema::hasColumns('summaries', [
        'id', 'summarizable_type', 'summarizable_id', 'municipality_id', 'meeting_id',
        'level', 'language', 'title', 'body', 'status', 'source_hash',
        'input_tokens', 'output_tokens', 'cost_cents', 'model', 'prompt_version',
    ]))->toBeTrue();
});

test('users table has is_admin column', function (): void {
    expect(Schema::hasColumn('users', 'is_admin'))->toBeTrue();
});

test('pending migrations restore missing database cache tables', function (): void {
    Schema::dropIfExists('cache_locks');
    Schema::dropIfExists('cache');

    DB::table('migrations')
        ->where('migration', '2026_06_11_084311_ensure_cache_tables_exist')
        ->delete();

    $this->artisan('migrate', ['--force' => true])
        ->assertSuccessful();

    expect(Schema::hasTable('cache'))->toBeTrue()
        ->and(Schema::hasTable('cache_locks'))->toBeTrue();
});
