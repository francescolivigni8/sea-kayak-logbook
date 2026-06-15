<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatchItem extends Model
{
    protected $fillable = [
        'import_batch_id',
        'paddle_session_id',
        'csv_row',
        'action',
        'external_ref',
        'session_date',
        'title',
        'distance_km',
        'duration_minutes',
        'before_snapshot',
        'after_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'distance_km' => 'float',
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PaddleSession::class, 'paddle_session_id');
    }
}
