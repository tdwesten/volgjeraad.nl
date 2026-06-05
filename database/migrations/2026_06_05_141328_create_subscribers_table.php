<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('level')->default('standard');
            $table->string('language')->default('nl');
            $table->char('confirmation_token', 64)->unique();
            $table->char('unsubscribe_token', 64)->unique();
            $table->dateTime('confirmed_at')->nullable()->index();
            $table->dateTime('unsubscribed_at')->nullable()->index();
            $table->string('lettermint_contact_id')->nullable();
            $table->string('consent_ip')->nullable();
            $table->text('consent_user_agent')->nullable();
            $table->timestamps();

            $table->unique(['municipality_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
