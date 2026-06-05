<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->string('ori_id');
            $table->decimal('position', 8, 2)->default(0);
            $table->text('name')->nullable();
            $table->json('raw_payload');
            $table->char('raw_payload_hash', 64)->index();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('attachments_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'ori_id']);
            $table->index(['meeting_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_items');
    }
};
