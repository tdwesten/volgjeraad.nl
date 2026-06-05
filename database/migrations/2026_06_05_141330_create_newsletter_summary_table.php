<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_summary', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('summary_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['newsletter_id', 'summary_id']);
            $table->index(['newsletter_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_summary');
    }
};
