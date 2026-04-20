<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_user_id',
        'slug',
        'name',
        'home_water',
        'timezone',
        'default_map_style',
        'is_public',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ProfileMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'profile_memberships')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PaddleSession::class);
    }

    public function plannedSessions(): HasMany
    {
        return $this->hasMany(PlannedSession::class);
    }

    public function sessionCategories(): HasMany
    {
        return $this->hasMany(SessionCategory::class);
    }

    public function requiresSetup(): bool
    {
        $settings = $this->settings ?? [];

        return (bool) data_get($settings, 'setup_required', false)
            && blank(data_get($settings, 'setup_completed_at'));
    }
}
