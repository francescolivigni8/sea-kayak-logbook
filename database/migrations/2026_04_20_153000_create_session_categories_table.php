<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('session_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['profile_id', 'slug']);
        });

        Schema::create('paddle_session_category', function (Blueprint $table) {
            $table->foreignId('paddle_session_id')->constrained('paddle_sessions')->cascadeOnDelete();
            $table->foreignId('session_category_id')->constrained('session_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['paddle_session_id', 'session_category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paddle_session_category');
        Schema::dropIfExists('session_categories');
    }
};
