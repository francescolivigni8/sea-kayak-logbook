export interface SessionProfileSummary {
    name: string;
    homeWater: string;
    timezone: string;
    defaultMapView?: {
        lat: number;
        lng: number;
        zoom: number;
    };
    kayaksOwned?: string[];
    paddlesOwned?: string[];
}

export interface SessionExistingAssets {
    gpxName: string | null;
    fitName: string | null;
    photoName: string | null;
    photoUrl: string | null;
}

export interface SessionFormDefaults {
    title: string;
    session_date: string;
    start_time_local: string;
    launch_name: string;
    launch_lat: string;
    launch_lng: string;
    landing_name: string;
    landing_lat: string;
    landing_lng: string;
    area_name: string;
    route_category: string;
    body_of_water: string;
    kayak_used: string;
    paddle_used: string;
    distance_km: string;
    duration_minutes: string;
    moving_minutes: string;
    wind_avg_ms: string;
    wind_gust_ms: string;
    wind_direction_deg: string;
    wind_beaufort: string;
    tide_state: string;
    current_knots: string;
    current_direction_deg: string;
    wave_height_m: string;
    swell_height_m: string;
    swell_period_s: string;
    swell_direction_deg: string;
    air_temp_c: string;
    sea_temp_c: string;
    rain_severity: string;
    wind_severity: string;
    temperature_severity: string;
    forecast_severity: string;
    visibility_code: string;
    weather_summary: string;
    route_summary: string;
    route_tags_text: string;
    partners_text: string;
    skills_text: string;
    manual_route_waypoints: string;
    successful_rolls_count: string;
    wet_exits_count: string;
    tow_rescues_count: string;
    what_went_well: string;
    improve_next: string;
    confidence_score: string;
    fatigue_score: string;
    decision_score: string;
    notes_public: string;
    notes_private: string;
    is_expedition: boolean;
    expedition_days: string;
    expedition_notes: string;
    autofill_weather: boolean;
    is_public: boolean;
}
