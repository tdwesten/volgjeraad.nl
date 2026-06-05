<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table): void {
            $table->id();
            $table->string('summarizable_type', 191);
            $table->unsignedBigInteger('summarizable_id');
            $table->index(['summarizable_type', 'summarizable_id']);
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level', 50)->index();
            $table->string('language', 10)->default('nl');
            $table->string('title');
            $table->longText('body');
            $table->text('impact_note')->nullable();
            $table->unsignedTinyInteger('confidence')->nullable();
            $table->string('status')->default('draft')->index();
            $table->json('flags')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('model');
            $table->string('prompt_version');
            $table->char('source_hash', 64)->index();
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamps();

            $table->unique(['summarizable_type', 'summarizable_id', 'level', 'language', 'source_hash'], 'summaries_polymorphic_content_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
