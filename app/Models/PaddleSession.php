<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PaddleSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'recorded_by_user_id',
        'external_ref',
        'session_date',
        'start_at',
        'timezone',
        'title',
        'area_name',
        'launch_name',
        'launch_lat',
        'launch_lng',
        'landing_name',
        'landing_lat',
        'landing_lng',
        'route_category',
        'body_of_water',
        'kayak_used',
        'paddle_used',
        'distance_km',
        'duration_minutes',
        'moving_minutes',
        'wind_avg_ms',
        'wind_gust_ms',
        'wind_direction_deg',
        'wind_beaufort',
        'tide_state',
        'current_knots',
        'current_direction_deg',
        'wave_height_m',
        'swell_height_m',
        'swell_period_s',
        'swell_direction_deg',
        'air_temp_c',
        'sea_temp_c',
        'rain_severity',
        'wind_severity',
        'temperature_severity',
        'forecast_severity',
        'visibility_code',
        'weather_summary',
        'route_summary',
        'notes_public',
        'notes_private',
        'expedition_notes',
        'skills',
        'route_tags',
        'partners',
        'successful_rolls_count',
        'wet_exits_count',
        'tow_rescues_count',
        'what_went_well',
        'improve_next',
        'confidence_score',
        'fatigue_score',
        'decision_score',
        'conditions_logged',
        'development_logged',
        'is_expedition',
        'expedition_days',
        'route_points',
        'route_profile',
        'garmin_gpx_name',
        'garmin_fit_name',
        'gpx_path',
        'fit_path',
        'session_photo_path',
        'session_photo_name',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'start_at' => 'datetime',
            'launch_lat' => 'float',
            'launch_lng' => 'float',
            'landing_lat' => 'float',
            'landing_lng' => 'float',
            'distance_km' => 'float',
            'wind_avg_ms' => 'float',
            'wind_gust_ms' => 'float',
            'current_knots' => 'float',
            'wave_height_m' => 'float',
            'swell_height_m' => 'float',
            'swell_period_s' => 'float',
            'air_temp_c' => 'float',
            'sea_temp_c' => 'float',
            'skills' => 'array',
            'route_tags' => 'array',
            'partners' => 'array',
            'route_profile' => 'array',
            'conditions_logged' => 'boolean',
            'development_logged' => 'boolean',
            'is_expedition' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
