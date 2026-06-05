<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evaluation_case_id')->constrained()->cascadeOnDelete();
            $table->string('prompt_version');
            $table->string('model');
            $table->string('status');
            $table->unsignedTinyInteger('score')->nullable();
            $table->json('checklist_results');
            $table->text('judge_feedback')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_runs');
    }
};
