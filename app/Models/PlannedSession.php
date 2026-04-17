<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlannedSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'created_by_user_id',
        'status',
        'plan_date',
        'start_at',
        'timezone',
        'title',
        'launch_name',
        'launch_lat',
        'launch_lng',
        'landing_name',
        'landing_lat',
        'landing_lng',
        'distance_km',
        'estimated_duration_minutes',
        'speed_knots',
        'route_points',
        'route_profile',
        'forecast_points',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'plan_date' => 'date',
            'start_at' => 'datetime',
            'launch_lat' => 'float',
            'launch_lng' => 'float',
            'landing_lat' => 'float',
            'landing_lng' => 'float',
            'distance_km' => 'float',
            'speed_knots' => 'float',
            'route_profile' => 'array',
            'forecast_points' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
