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
        Schema::create('paddle_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('external_ref')->nullable()->index();
            $table->date('session_date')->index();
            $table->dateTimeTz('start_at')->nullable();
            $table->string('timezone')->default('Atlantic/Reykjavik');
            $table->string('title');
            $table->string('area_name')->nullable();
            $table->string('launch_name')->nullable();
            $table->decimal('launch_lat', 10, 7)->nullable();
            $table->decimal('launch_lng', 10, 7)->nullable();
            $table->string('landing_name')->nullable();
            $table->decimal('landing_lat', 10, 7)->nullable();
            $table->decimal('landing_lng', 10, 7)->nullable();
            $table->string('route_category')->default('journey');
            $table->string('body_of_water')->nullable();
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->unsignedInteger('moving_minutes')->nullable();
            $table->decimal('wind_avg_ms', 5, 2)->nullable();
            $table->decimal('wind_gust_ms', 5, 2)->nullable();
            $table->unsignedSmallInteger('wind_direction_deg')->nullable();
            $table->unsignedTinyInteger('wind_beaufort')->nullable();
            $table->string('tide_state')->nullable();
            $table->decimal('current_knots', 5, 2)->nullable();
            $table->unsignedSmallInteger('current_direction_deg')->nullable();
            $table->decimal('wave_height_m', 5, 2)->nullable();
            $table->decimal('swell_height_m', 5, 2)->nullable();
            $table->decimal('swell_period_s', 5, 2)->nullable();
            $table->unsignedSmallInteger('swell_direction_deg')->nullable();
            $table->decimal('air_temp_c', 5, 2)->nullable();
            $table->decimal('sea_temp_c', 5, 2)->nullable();
            $table->string('rain_severity')->nullable();
            $table->string('wind_severity')->nullable();
            $table->string('temperature_severity')->nullable();
            $table->string('forecast_severity')->nullable();
            $table->string('visibility_code')->nullable();
            $table->text('weather_summary')->nullable();
            $table->text('route_summary')->nullable();
            $table->longText('notes_public')->nullable();
            $table->longText('notes_private')->nullable();
            $table->longText('expedition_notes')->nullable();
            $table->json('skills')->nullable();
            $table->json('route_tags')->nullable();
            $table->json('partners')->nullable();
            $table->unsignedInteger('successful_rolls_count')->default(0);
            $table->unsignedInteger('wet_exits_count')->default(0);
            $table->unsignedInteger('tow_rescues_count')->default(0);
            $table->text('what_went_well')->nullable();
            $table->text('improve_next')->nullable();
            $table->unsignedTinyInteger('confidence_score')->nullable();
            $table->unsignedTinyInteger('fatigue_score')->nullable();
            $table->unsignedTinyInteger('decision_score')->nullable();
            $table->boolean('conditions_logged')->default(false);
            $table->boolean('development_logged')->default(false);
            $table->boolean('is_expedition')->default(false);
            $table->unsignedTinyInteger('expedition_days')->nullable();
            $table->longText('route_points')->nullable();
            $table->json('route_profile')->nullable();
            $table->string('garmin_gpx_name')->nullable();
            $table->string('garmin_fit_name')->nullable();
            $table->string('gpx_path')->nullable();
            $table->string('fit_path')->nullable();
            $table->string('session_photo_path')->nullable();
            $table->string('session_photo_name')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->unique(['profile_id', 'external_ref']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paddle_sessions');
    }
};
