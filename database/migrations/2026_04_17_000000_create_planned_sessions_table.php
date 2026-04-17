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
        Schema::create('planned_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('planned')->index();
            $table->date('plan_date')->index();
            $table->dateTimeTz('start_at')->nullable();
            $table->string('timezone')->default('Atlantic/Reykjavik');
            $table->string('title');
            $table->string('launch_name')->nullable();
            $table->decimal('launch_lat', 10, 7)->nullable();
            $table->decimal('launch_lng', 10, 7)->nullable();
            $table->string('landing_name')->nullable();
            $table->decimal('landing_lat', 10, 7)->nullable();
            $table->decimal('landing_lng', 10, 7)->nullable();
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->decimal('speed_knots', 5, 2)->default(3.5);
            $table->longText('route_points')->nullable();
            $table->json('route_profile')->nullable();
            $table->json('forecast_points')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('planned_sessions');
    }
};
