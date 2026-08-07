<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'qris_merchant_id',
        'callback_url',
        'is_active',
        'invoice_ttl_minutes',
        'unique_code_min',
        'unique_code_max',
        'notification_config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'invoice_ttl_minutes' => 'integer',
            'unique_code_min' => 'integer',
            'unique_code_max' => 'integer',
            'notification_config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<QrisMerchant, $this>
     */
    public function qrisMerchant(): BelongsTo
    {
        return $this->belongsTo(QrisMerchant::class);
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
