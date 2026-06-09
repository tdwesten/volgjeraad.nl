<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('summary_source')->nullable()->after('summarized_at');
            $table->dateTime('source_resolved_at')->nullable()->after('summary_source');
            $table->dateTime('notule_detected_at')->nullable()->after('source_resolved_at');
            $table->foreignId('notule_media_object_id')->nullable()->after('notule_detected_at')
                ->constrained('media_objects')->nullOnDelete();
            $table->string('summary_skipped_reason')->nullable()->after('notule_media_object_id');
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('notule_media_object_id');
            $table->dropColumn(['summary_source', 'source_resolved_at', 'notule_detected_at', 'summary_skipped_reason']);
        });
    }
};
