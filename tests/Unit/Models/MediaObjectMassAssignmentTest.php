<?php

use App\Models\AgendaItem;
use App\Models\MediaObject;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a media object persists all ingest fields, including the NOT NULL raw_payload_hash', function (): void {
    $item = AgendaItem::factory()->create();

    // Vóór de fix had MediaObject een $fillable die o.a. raw_payload_hash wegliet,
    // waardoor deze create de NOT NULL-kolom liet falen (1364) en de hele
    // document-ingest blokkeerde.
    $media = MediaObject::create([
        'agenda_item_id' => $item->id,
        'ori_id' => 'doc-1',
        'name' => 'Besluitenlijst 3 juni 2026',
        'file_name' => 'besluitenlijst.pdf',
        'content_type' => 'application/pdf',
        'url' => 'https://ori.example/doc.pdf',
        'raw_payload_hash' => str_repeat('a', 64),
        'has_text' => true,
    ])->fresh();

    expect($media->raw_payload_hash)->toBe(str_repeat('a', 64));
    expect($media->content_type)->toBe('application/pdf');
    expect($media->url)->toBe('https://ori.example/doc.pdf');
    expect($media->has_text)->toBeTrue();
});
