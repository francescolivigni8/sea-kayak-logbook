<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 40)->default('garmin_csv');
            $table->string('file_name')->nullable();
            $table->string('status', 24)->default('finished');
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('selected_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('summary')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->foreignId('undone_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['profile_id', 'created_at']);
            $table->index(['profile_id', 'undone_at']);
        });

        Schema::create('import_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paddle_session_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('csv_row')->nullable();
            $table->string('action', 24);
            $table->string('external_ref')->nullable();
            $table->date('session_date')->nullable();
            $table->string('title')->nullable();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamps();

            $table->index(['import_batch_id', 'action']);
            $table->index(['paddle_session_id']);
        });

        Schema::table('paddle_sessions', function (Blueprint $table) {
            $table->foreignId('import_batch_id')
                ->nullable()
                ->after('recorded_by_user_id')
                ->constrained('import_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paddle_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('import_batch_id');
        });

        Schema::dropIfExists('import_batch_items');
        Schema::dropIfExists('import_batches');
    }
};
