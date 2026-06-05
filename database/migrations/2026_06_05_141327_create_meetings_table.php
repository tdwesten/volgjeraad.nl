<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('ori_id');
            $table->string('type')->index();
            $table->string('committee_ori_id')->nullable();
            $table->string('committee_name')->nullable();
            $table->string('name')->nullable();
            $table->dateTime('starts_at')->nullable()->index();
            $table->string('status')->nullable();
            $table->text('source_url')->nullable();
            $table->json('raw_payload');
            $table->char('raw_payload_hash', 64)->index();
            $table->string('ingest_mode')->index();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('agenda_ingested_at')->nullable();
            $table->dateTime('summarized_at')->nullable();
            $table->timestamps();

            $table->unique(['municipality_id', 'ori_id']);
            $table->index(['municipality_id', 'type', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
