<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_objects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agenda_item_id')->constrained()->cascadeOnDelete();
            $table->string('ori_id');
            $table->decimal('position', 8, 2)->nullable();
            $table->text('name')->nullable();
            $table->string('file_name')->nullable();
            $table->string('content_type')->nullable();
            $table->unsignedBigInteger('size_in_bytes')->nullable();
            $table->text('url')->nullable();
            $table->text('original_url')->nullable();
            $table->longText('text')->nullable();
            $table->longText('md_text')->nullable();
            $table->json('text_pages')->nullable();
            $table->boolean('has_text')->default(false)->index();
            $table->string('text_missing_reason')->nullable();
            $table->char('raw_payload_hash', 64);
            $table->timestamps();

            $table->unique(['agenda_item_id', 'ori_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_objects');
    }
};
