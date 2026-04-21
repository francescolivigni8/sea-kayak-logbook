<?php

namespace App\Http\Requests;

use App\Models\PaddleSession;
use Illuminate\Foundation\Http\FormRequest;

class UpsertPaddleSessionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_expedition' => $this->boolean('is_expedition'),
            'is_public' => $this->boolean('is_public'),
            'autofill_weather' => $this->boolean('autofill_weather'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $session = $this->route('session');
        $hasExistingRouteFile = $session instanceof PaddleSession
            && (filled($session->gpx_path) || filled($session->fit_path));
        $distanceRules = ['nullable', 'numeric', 'min:0'];

        if (! $hasExistingRouteFile) {
            $distanceRules[] = 'required_without_all:gpx_file,fit_file';
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'session_date' => ['required', 'date'],
            'start_time_local' => ['nullable', 'date_format:H:i'],
            'launch_name' => ['nullable', 'string', 'max:255'],
            'launch_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'launch_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'landing_name' => ['nullable', 'string', 'max:255'],
            'landing_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'landing_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'area_name' => ['nullable', 'string', 'max:255'],
            'route_category' => ['required', 'string', 'max:100'],
            'body_of_water' => ['nullable', 'string', 'max:100'],
            'kayak_used' => ['nullable', 'string', 'max:255'],
            'paddle_used' => ['nullable', 'string', 'max:255'],
            'distance_km' => $distanceRules,
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'moving_minutes' => ['nullable', 'integer', 'min:0'],
            'wind_avg_ms' => ['nullable', 'numeric', 'min:0'],
            'wind_gust_ms' => ['nullable', 'numeric', 'min:0'],
            'wind_direction_deg' => ['nullable', 'integer', 'min:0', 'max:360'],
            'wind_beaufort' => ['nullable', 'integer', 'min:0', 'max:12'],
            'tide_state' => ['nullable', 'string', 'max:100'],
            'current_knots' => ['nullable', 'numeric', 'min:0'],
            'current_direction_deg' => ['nullable', 'integer', 'min:0', 'max:360'],
            'wave_height_m' => ['nullable', 'numeric', 'min:0'],
            'swell_height_m' => ['nullable', 'numeric', 'min:0'],
            'swell_period_s' => ['nullable', 'numeric', 'min:0'],
            'swell_direction_deg' => ['nullable', 'integer', 'min:0', 'max:360'],
            'air_temp_c' => ['nullable', 'numeric'],
            'sea_temp_c' => ['nullable', 'numeric'],
            'rain_severity' => ['nullable', 'string', 'max:30'],
            'wind_severity' => ['nullable', 'string', 'max:30'],
            'temperature_severity' => ['nullable', 'string', 'max:30'],
            'forecast_severity' => ['nullable', 'string', 'max:30'],
            'visibility_code' => ['nullable', 'string', 'max:30'],
            'weather_summary' => ['nullable', 'string'],
            'route_summary' => ['nullable', 'string'],
            'route_tags_text' => ['nullable', 'string'],
            'category_names_text' => ['nullable', 'string', 'max:1000'],
            'partners_text' => ['nullable', 'string'],
            'skills_text' => ['nullable', 'string'],
            'manual_route_waypoints' => ['nullable', 'string'],
            'successful_rolls_count' => ['nullable', 'integer', 'min:0'],
            'wet_exits_count' => ['nullable', 'integer', 'min:0'],
            'tow_rescues_count' => ['nullable', 'integer', 'min:0'],
            'what_went_well' => ['nullable', 'string'],
            'improve_next' => ['nullable', 'string'],
            'confidence_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'fatigue_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'decision_score' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes_public' => ['nullable', 'string'],
            'notes_private' => ['nullable', 'string'],
            'is_expedition' => ['sometimes', 'boolean'],
            'expedition_days' => ['nullable', 'integer', 'min:2', 'max:100'],
            'expedition_notes' => ['nullable', 'string'],
            'is_public' => ['sometimes', 'boolean'],
            'autofill_weather' => ['sometimes', 'boolean'],
            'gpx_file' => ['nullable', 'file', 'mimes:gpx,xml', 'max:20480'],
            'fit_file' => ['nullable', 'file', 'extensions:fit', 'max:20480'],
            'session_photo' => ['nullable', 'image', 'max:8192'],
        ];
    }
}
