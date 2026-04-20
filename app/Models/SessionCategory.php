<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SessionCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'profile_id',
        'name',
        'slug',
        'color',
        'description',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(PaddleSession::class, 'paddle_session_category')
            ->withTimestamps()
            ->orderByDesc('session_date')
            ->orderByDesc('paddle_sessions.id');
    }
}
