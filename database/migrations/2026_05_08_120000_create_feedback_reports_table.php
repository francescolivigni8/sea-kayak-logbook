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
        Schema::create('feedback_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 24);
            $table->string('subject', 160);
            $table->string('page_context', 180)->nullable();
            $table->text('message');
            $table->string('submitted_from_path', 255)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('status', 24)->default('new');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_reports');
    }
};
