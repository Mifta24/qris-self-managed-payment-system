<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris per PERCOBAAN pengiriman callback, bukan per event. Append-only:
 * tidak ada updated_at, tidak ada soft delete, tidak ada update.
 */
final class CallbackLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'invoice_id',
        'event_id',
        'event_type',
        'url',
        'attempt',
        'request_payload',
        'request_signature',
        'response_status',
        'response_body',
        'error_message',
        'duration_ms',
        'succeeded_at',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'request_payload' => 'array',
            'response_status' => 'integer',
            'duration_ms' => 'integer',
            'succeeded_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
