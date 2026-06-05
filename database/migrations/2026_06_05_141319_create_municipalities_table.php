<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('ori_index');
            $table->string('timezone')->default('Europe/Amsterdam');
            $table->boolean('active')->default(true);
            $table->date('launch_date')->nullable();
            $table->unsignedTinyInteger('backfill_recent_meetings')->default(2);
            $table->string('ai_model_summary')->default('gpt-4o-mini');
            $table->string('ai_model_eval')->default('gpt-4o-mini');
            $table->string('raad_pattern')->default('raadsvergadering');
            $table->string('sender_name')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
