<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedbackReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_id',
        'kind',
        'subject',
        'page_context',
        'message',
        'submitted_from_path',
        'user_agent',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
