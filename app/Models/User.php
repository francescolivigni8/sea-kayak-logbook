<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable([
    'name',
    'email',
    'password',
    'accepted_terms_at',
    'accepted_privacy_at',
    'accepted_terms_version',
    'accepted_privacy_version',
])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'accepted_terms_at' => 'datetime',
            'accepted_privacy_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function ownedProfiles(): HasMany
    {
        return $this->hasMany(Profile::class, 'owner_user_id');
    }

    public function profileMemberships(): HasMany
    {
        return $this->hasMany(ProfileMembership::class);
    }

    public function accessibleProfiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_memberships')
            ->withPivot(['role'])
            ->withTimestamps();
    }

    public function resolveActiveProfile(): Profile
    {
        $owned = $this->ownedProfiles()->first();

        if ($owned) {
            ProfileMembership::firstOrCreate(
                [
                    'profile_id' => $owned->id,
                    'user_id' => $this->id,
                ],
                [
                    'role' => 'owner',
                ],
            );

            return $owned;
        }

        $accessible = $this->accessibleProfiles()->first();

        if ($accessible) {
            return $accessible;
        }

        $base = Str::slug($this->name ?: Str::before($this->email, '@')) ?: 'sea-kayak-logbook';

        $profile = Profile::create([
            'owner_user_id' => $this->id,
            'slug' => Str::limit($base.'-'.$this->id, 64, ''),
            'name' => trim(($this->name ?: 'My').' Sea Kayak Logbook'),
            'home_water' => 'Faxafloi',
            'timezone' => 'Atlantic/Reykjavik',
            'default_map_style' => 'chart',
            'is_public' => false,
            'settings' => [
                'setup_required' => true,
            ],
        ]);

        ProfileMembership::firstOrCreate(
            [
                'profile_id' => $profile->id,
                'user_id' => $this->id,
            ],
            [
                'role' => 'owner',
            ],
        );

        return $profile;
    }

    public function allProfiles(): Collection
    {
        return $this->ownedProfiles
            ->concat($this->accessibleProfiles)
            ->unique('id')
            ->values();
    }

    public function canViewOwnerTools(): bool
    {
        $ownerEmails = collect(config('kayak.owner_emails', []))
            ->map(fn (string $email) => trim(strtolower($email)))
            ->filter();

        if ($ownerEmails->isEmpty()) {
            return app()->environment(['local', 'testing']);
        }

        return $ownerEmails->contains(strtolower($this->email));
    }
}
