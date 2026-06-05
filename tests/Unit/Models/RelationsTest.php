<?php

use App\Enums\IngestMode;
use App\Enums\MeetingType;
use App\Enums\SummaryLevel;
use App\Models\AgendaItem;
use App\Models\MediaObject;
use App\Models\Meeting;
use App\Models\Municipality;
use App\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('municipality has many meetings', function (): void {
    $municipality = Municipality::factory()->create();
    Meeting::factory()->count(3)->create(['municipality_id' => $municipality->id]);

    expect($municipality->meetings()->count())->toBe(3);
});

test('municipality has many subscribers', function (): void {
    $municipality = Municipality::factory()->create();
    Subscriber::factory()->count(2)->create(['municipality_id' => $municipality->id]);

    expect($municipality->subscribers()->count())->toBe(2);
});

test('meeting belongs to municipality', function (): void {
    $meeting = Meeting::factory()->create();

    expect($meeting->municipality)->toBeInstanceOf(Municipality::class);
});

test('meeting has many agenda items', function (): void {
    $meeting = Meeting::factory()->create();
    AgendaItem::factory()->count(5)->create(['meeting_id' => $meeting->id]);

    expect($meeting->agendaItems()->count())->toBe(5);
});

test('agenda item has many media objects', function (): void {
    $agendaItem = AgendaItem::factory()->create();
    MediaObject::factory()->count(2)->create(['agenda_item_id' => $agendaItem->id]);

    expect($agendaItem->mediaObjects()->count())->toBe(2);
});

test('scopeActive filters inactive municipalities', function (): void {
    Municipality::factory()->create(['active' => true]);
    Municipality::factory()->create(['active' => false]);

    expect(Municipality::active()->count())->toBe(1);
});

test('scopeCouncil filters council meetings', function (): void {
    Meeting::factory()->council()->create();
    Meeting::factory()->create(['type' => MeetingType::Other->value]);

    expect(Meeting::council()->count())->toBe(1);
});

test('scopeSummarizable filters summarizable meetings', function (): void {
    Meeting::factory()->summarizable()->create();
    Meeting::factory()->create(['ingest_mode' => IngestMode::MetadataOnly->value]);

    expect(Meeting::summarizable()->count())->toBe(1);
});

test('scopeConfirmed filters confirmed subscribers', function (): void {
    $municipality = Municipality::factory()->create();
    Subscriber::factory()->confirmed()->create(['municipality_id' => $municipality->id]);
    Subscriber::factory()->unconfirmed()->create(['municipality_id' => $municipality->id]);

    expect(Subscriber::confirmed()->count())->toBe(1);
});

test('scopeForLevel filters subscribers by level', function (): void {
    $municipality = Municipality::factory()->create();
    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $municipality->id,
        'level' => SummaryLevel::Standard->value,
    ]);
    Subscriber::factory()->confirmed()->create([
        'municipality_id' => $municipality->id,
        'level' => SummaryLevel::Simple->value,
    ]);

    expect(Subscriber::confirmed()->forLevel(SummaryLevel::Standard)->count())->toBe(1);
});

test('scopeWithText filters media objects with text', function (): void {
    $agendaItem = AgendaItem::factory()->create();
    MediaObject::factory()->withText()->create(['agenda_item_id' => $agendaItem->id]);
    MediaObject::factory()->empty()->create(['agenda_item_id' => $agendaItem->id]);

    expect(MediaObject::withText()->count())->toBe(1);
});

test('meeting shouldSummarize returns true for summarize mode', function (): void {
    $meeting = Meeting::factory()->summarizable()->make();

    expect($meeting->shouldSummarize())->toBeTrue();
});

test('meeting shouldSummarize returns false for metadata only', function (): void {
    $meeting = Meeting::factory()->make(['ingest_mode' => IngestMode::MetadataOnly->value]);

    expect($meeting->shouldSummarize())->toBeFalse();
});
