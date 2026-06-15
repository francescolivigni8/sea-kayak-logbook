<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'profile_id',
        'user_id',
        'kind',
        'file_name',
        'status',
        'rows_count',
        'selected_count',
        'created_count',
        'updated_count',
        'skipped_count',
        'summary',
        'undone_at',
        'undone_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'undone_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function undoneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'undone_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImportBatchItem::class);
    }
}
