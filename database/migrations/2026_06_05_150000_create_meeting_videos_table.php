<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('youtube_video_id')->nullable();
            $table->text('video_url')->nullable();
            $table->unsignedTinyInteger('match_confidence')->nullable();
            $table->text('match_reason')->nullable();
            $table->json('candidates')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->string('transcript_source')->nullable();
            $table->string('transcript_error')->nullable();
            $table->dateTime('transcript_fetched_at')->nullable();
            $table->string('status')->default('pending')->index();
            // Twee gescheiden tellers (review #114 MAJOR): zoeken/matchen vs transcript-fetch.
            $table->unsignedInteger('match_attempts')->default(0);
            $table->unsignedInteger('transcript_attempts')->default(0);
            $table->dateTime('last_attempt_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_videos');
    }
};
